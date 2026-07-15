<?php

namespace App\Http\Controllers\Akademik;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Traits\ApiResponser;
use App\Traits\LogsAudit;
use App\Http\Controllers\Controller;
use App\Traits\ConsumeMicroserviceService;

class AkademikController extends Controller
{
    use ConsumeMicroserviceService, ApiResponser, LogsAudit;

    private $baseUri, $secret, $reqUrl;

    // Kredensial service lain untuk validasi cross-service ID
    private $classBaseUri, $classSecret, $classReqUrl;
    private $guruBaseUri,  $guruSecret,  $guruReqUrl;
    private $siswaBaseUri, $siswaSecret, $siswaReqUrl;
    private $mapelBaseUri, $mapelSecret, $mapelReqUrl;

    public function __construct()
    {
        $this->reqUrl       = config('gateway.akademik_prefix');
        $this->baseUri      = config('services.akademik.base_uri');
        $this->secret       = config('services.akademik.secret');

        $this->classBaseUri = config('services.class.base_uri');
        $this->classSecret  = config('services.class.secret');
        $this->classReqUrl  = config('gateway.class_prefix');

        $this->guruBaseUri  = config('services.guru.base_uri');
        $this->guruSecret   = config('services.guru.secret');
        $this->guruReqUrl   = config('gateway.guru_prefix');

        $this->siswaBaseUri = config('services.siswa.base_uri');
        $this->siswaSecret  = config('services.siswa.secret');
        $this->siswaReqUrl  = config('gateway.siswa_prefix');

        $this->mapelBaseUri = config('services.mapel.base_uri');
        $this->mapelSecret  = config('services.mapel.secret');
        $this->mapelReqUrl  = config('gateway.mapel_prefix');
    }

    // POST /akademik/kelas/assign — SuperAdmin, Admin
    // Validasi siswa_id ke SiswaService + ambil limit_siswa dari ClassMicroservices
    public function assignSiswa(Request $request)
    {
        try {
            if (!$request->filled('kelas_id')) {
                return $this->response('Field kelas_id wajib diisi.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Validasi siswa_id: pastikan masih aktif di SiswaService
            if ($request->filled('siswa_id')) {
                $siswaData = $this->decode(
                    $this->callService($this->siswaBaseUri, $this->siswaSecret, 'GET', $this->siswaReqUrl, ['idSiswa' => $request->siswa_id])
                );
                if (($siswaData['resCode'] ?? null) !== Response::HTTP_OK) {
                    return $this->response('Siswa tidak ditemukan atau sudah tidak aktif.', Response::HTTP_NOT_FOUND);
                }
            }

            // Ambil info kelas untuk mendapatkan limit_siswa (sekaligus validasi kelas_id)
            $kelasData = $this->decode(
                $this->callService($this->classBaseUri, $this->classSecret, 'GET', $this->classReqUrl, ['idKelas' => $request->kelas_id])
            );

            if (($kelasData['resCode'] ?? null) !== Response::HTTP_OK) {
                return $this->response('Kelas tidak ditemukan.', Response::HTTP_NOT_FOUND);
            }

            $limitSiswa = $kelasData['data']['limitSiswa'] ?? null;
            if (!$limitSiswa) {
                return $this->response('Data limit kelas tidak tersedia.', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $payload = array_merge($request->all(), ['limit_siswa' => $limitSiswa]);
            $response = $this->performRequest('POST', "{$this->reqUrl}/kelas/assign", $payload);
            $decode   = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->auditLog('created', 'siswa_kelas', $decode['data']['idSiswaKelas'] ?? null, [
                    'siswa_id'     => $request->siswa_id,
                    'kelas_id'     => $request->kelas_id,
                    'tahun_ajaran' => $request->tahun_ajaran,
                    'semester'     => $request->semester,
                ]);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // PATCH /akademik/kelas/assign/{id} — SuperAdmin, Admin
    // Pindah kelas dalam semester yang sama; butuh limit_siswa dari ClassMicroservices
    public function pindahKelas(Request $request, $id)
    {
        try {
            if (!$request->filled('kelas_id')) {
                return $this->response('Field kelas_id wajib diisi.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kelasData = $this->decode(
                $this->callService($this->classBaseUri, $this->classSecret, 'GET', $this->classReqUrl, ['idKelas' => $request->kelas_id])
            );

            if (($kelasData['resCode'] ?? null) !== Response::HTTP_OK) {
                return $this->response('Kelas tujuan tidak ditemukan.', Response::HTTP_NOT_FOUND);
            }

            $limitSiswa = $kelasData['data']['limitSiswa'] ?? null;
            if (!$limitSiswa) {
                return $this->response('Data limit kelas tidak tersedia.', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $payload  = array_merge($request->only('kelas_id'), ['limit_siswa' => $limitSiswa]);
            $response = $this->performRequest('PATCH', "{$this->reqUrl}/kelas/assign/{$id}", $payload);
            $decode   = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'siswa_kelas', $id, [
                    'kelas_id_baru' => $request->kelas_id,
                ]);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /akademik/kelas/assign/{id} — SuperAdmin, Admin
    public function removeSiswa(Request $request, $id)
    {
        try {
            $response = $this->performRequest('DELETE', "{$this->reqUrl}/kelas/assign/{$id}");
            $decode   = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_ACCEPTED) {
                $this->auditLog('deleted', 'siswa_kelas', $id, []);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/kelas/{kelas_id}/siswa — semua role
    public function getSiswaByKelas(Request $request, $kelasId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/kelas/{$kelasId}/siswa", $request->only(['tahun_ajaran', 'semester']));
    }

    // GET /akademik/siswa/{siswa_id}/kelas — semua role
    public function getKelasBySiswa(Request $request, $siswaId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/siswa/{$siswaId}/kelas", $request->only(['tahun_ajaran', 'semester']));
    }

    // POST /akademik/pengampu — SuperAdmin, Admin
    // Validasi guru_id ke GuruService, mapel_id ke MapelService, kelas_id ke ClassService
    public function assignGuru(Request $request)
    {
        try {
            if ($request->filled('guru_id')) {
                $guruData = $this->decode(
                    $this->callService($this->guruBaseUri, $this->guruSecret, 'GET', $this->guruReqUrl, ['idGuru' => $request->guru_id])
                );
                if (($guruData['resCode'] ?? null) !== Response::HTTP_OK) {
                    return $this->response('Guru tidak ditemukan atau sudah tidak aktif.', Response::HTTP_NOT_FOUND);
                }
            }

            if ($request->filled('mapel_id')) {
                $mapelData = $this->decode(
                    $this->callService($this->mapelBaseUri, $this->mapelSecret, 'GET', $this->mapelReqUrl, ['idPelajaran' => $request->mapel_id])
                );
                if (($mapelData['resCode'] ?? null) !== Response::HTTP_OK) {
                    return $this->response('Mata pelajaran tidak ditemukan.', Response::HTTP_NOT_FOUND);
                }
            }

            if ($request->filled('kelas_id')) {
                $kelasData = $this->decode(
                    $this->callService($this->classBaseUri, $this->classSecret, 'GET', $this->classReqUrl, ['idKelas' => $request->kelas_id])
                );
                if (($kelasData['resCode'] ?? null) !== Response::HTTP_OK) {
                    return $this->response('Kelas tidak ditemukan.', Response::HTTP_NOT_FOUND);
                }
            }

            $response = $this->performRequest('POST', "{$this->reqUrl}/pengampu", $request->all());
            $decode   = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->auditLog('created', 'pengampu_mapel', $decode['data']['idPengampuMapel'] ?? null, [
                    'guru_id'      => $request->guru_id,
                    'mapel_id'     => $request->mapel_id,
                    'kelas_id'     => $request->kelas_id,
                    'tahun_ajaran' => $request->tahun_ajaran,
                    'semester'     => $request->semester,
                ]);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /akademik/pengampu/{id} — SuperAdmin, Admin
    public function removeGuru(Request $request, $id)
    {
        try {
            $response = $this->performRequest('DELETE', "{$this->reqUrl}/pengampu/{$id}");
            $decode   = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_ACCEPTED) {
                $this->auditLog('deleted', 'pengampu_mapel', $id, []);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/kelas/{kelas_id}/pengampu — semua role
    public function getPengampuByKelas(Request $request, $kelasId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/kelas/{$kelasId}/pengampu", $request->only(['tahun_ajaran', 'semester']));
    }

    // GET /akademik/guru/{guru_id}/mapel — semua role
    public function getMapelByGuru(Request $request, $guruId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/guru/{$guruId}/mapel", $request->only(['tahun_ajaran', 'semester']));
    }

    // GET /akademik/mapel/{mapel_id}/guru — semua role
    public function getGuruByMapel(Request $request, $mapelId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/mapel/{$mapelId}/guru", $request->only(['kelas_id', 'tahun_ajaran', 'semester']));
    }

    // GET /akademik/siswa/{siswa_id}/kelas/riwayat — SuperAdmin, Admin
    public function getRiwayatSiswa(Request $request, $siswaId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/siswa/{$siswaId}/kelas/riwayat", $request->only(['tahun_ajaran', 'semester']));
    }

    // GET /akademik/kelas/{kelas_id}/siswa/riwayat — SuperAdmin, Admin
    public function getRiwayatKelas(Request $request, $kelasId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/kelas/{$kelasId}/siswa/riwayat", $request->only(['tahun_ajaran', 'semester']));
    }

    // PATCH /akademik/pengampu/{id} — SuperAdmin, Admin
    // Validasi guru_id ke GuruService sebelum ganti pengampu
    public function gantiGuru(Request $request, $id)
    {
        try {
            if ($request->filled('guru_id')) {
                $guruData = $this->decode(
                    $this->callService($this->guruBaseUri, $this->guruSecret, 'GET', $this->guruReqUrl, ['idGuru' => $request->guru_id])
                );
                if (($guruData['resCode'] ?? null) !== Response::HTTP_OK) {
                    return $this->response('Guru tidak ditemukan atau sudah tidak aktif.', Response::HTTP_NOT_FOUND);
                }
            }

            $response = $this->performRequest('PATCH', "{$this->reqUrl}/pengampu/{$id}", $request->only('guru_id'));
            $decode   = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'pengampu_mapel', $id, [
                    'guru_id_baru' => $request->guru_id,
                ]);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/guru/{guru_id}/mapel/riwayat — SuperAdmin, Admin
    public function getRiwayatGuru(Request $request, $guruId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/guru/{$guruId}/mapel/riwayat", $request->only(['tahun_ajaran', 'semester']));
    }

    // GET /akademik/mapel/{mapel_id}/guru/riwayat — SuperAdmin, Admin
    public function getRiwayatMapel(Request $request, $mapelId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/mapel/{$mapelId}/guru/riwayat", $request->only(['kelas_id', 'tahun_ajaran', 'semester']));
    }

    // GET /akademik/semester/aktif — semua role
    public function getSemesterAktif()
    {
        return $this->performRequest('GET', "{$this->reqUrl}/semester/aktif");
    }

    // POST /akademik/semester/aktif — SuperAdmin, Admin
    public function setSemesterAktif(Request $request)
    {
        try {
            $response = $this->performRequest('POST', "{$this->reqUrl}/semester/aktif", $request->all());
            $decode   = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->auditLog('created', 'semester_aktif', $decode['data']['idSemesterAktif'] ?? null, [
                    'tahun_ajaran' => $request->tahun_ajaran,
                    'semester'     => $request->semester,
                ]);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/semester/riwayat — semua role
    public function getRiwayatSemester()
    {
        return $this->performRequest('GET', "{$this->reqUrl}/semester/riwayat");
    }

    // GET /akademik/jam — semua role
    public function getJamPelajaran()
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jam");
    }

    // POST /akademik/jam — SuperAdmin, Admin
    public function storeJam(Request $request)
    {
        try {
            $response = $this->performRequest('POST', "{$this->reqUrl}/jam", $request->all());
            $decode   = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->auditLog('created', 'jam_pelajaran', $decode['data']['idJam'] ?? null, $request->only(['ke', 'jam_mulai', 'jam_selesai']));
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // PATCH /akademik/jam/{id} — SuperAdmin, Admin
    public function updateJam(Request $request, $id)
    {
        try {
            $response = $this->performRequest('PATCH', "{$this->reqUrl}/jam/{$id}", $request->all());
            $decode   = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'jam_pelajaran', $id, $request->only(['ke', 'jam_mulai', 'jam_selesai']));
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /akademik/jam/{id} — SuperAdmin, Admin
    public function destroyJam($id)
    {
        try {
            $response = $this->performRequest('DELETE', "{$this->reqUrl}/jam/{$id}");
            $decode   = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_ACCEPTED) {
                $this->auditLog('deleted', 'jam_pelajaran', $id, []);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /akademik/jadwal — SuperAdmin, Admin
    public function storeJadwal(Request $request)
    {
        try {
            $response = $this->performRequest('POST', "{$this->reqUrl}/jadwal", $request->all());
            $decode   = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->auditLog('created', 'jadwal_pelajaran', $decode['data']['idJadwal'] ?? null, [
                    'pengampu_mapel_id' => $request->pengampu_mapel_id,
                    'hari'              => $request->hari,
                ]);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // PATCH /akademik/jadwal/{id} — SuperAdmin, Admin
    public function updateJadwal(Request $request, $id)
    {
        try {
            $response = $this->performRequest('PATCH', "{$this->reqUrl}/jadwal/{$id}", $request->all());
            $decode   = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'jadwal_pelajaran', $id, $request->only(['hari', 'jam_mulai_id', 'jam_selesai_id', 'ruangan', 'catatan']));
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /akademik/jadwal/{id} — SuperAdmin, Admin
    public function removeJadwal($id)
    {
        try {
            $response = $this->performRequest('DELETE', "{$this->reqUrl}/jadwal/{$id}");
            $decode   = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_ACCEPTED) {
                $this->auditLog('deleted', 'jadwal_pelajaran', $id, []);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/jadwal/pengampu/{id} — semua role
    public function getJadwalByPengampu($pengampuId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jadwal/pengampu/{$pengampuId}");
    }

    // GET /akademik/jadwal/kelas/{id} — semua role
    public function getJadwalByKelas(Request $request, $kelasId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jadwal/kelas/{$kelasId}", $request->only(['tahun_ajaran', 'semester']));
    }

    // GET /akademik/jadwal/guru/{id} — semua role
    public function getJadwalByGuru(Request $request, $guruId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jadwal/guru/{$guruId}", $request->only(['tahun_ajaran', 'semester']));
    }

    // GET /akademik/jadwal/siswa/{id} — semua role
    public function getJadwalBySiswa(Request $request, $siswaId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jadwal/siswa/{$siswaId}", $request->only(['tahun_ajaran', 'semester']));
    }

    // GET /akademik/jadwal/pengampu/{id}/riwayat — SuperAdmin, Admin
    public function getRiwayatJadwalByPengampu($pengampuId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jadwal/pengampu/{$pengampuId}/riwayat");
    }

    // GET /akademik/jadwal/kelas/{id}/riwayat — SuperAdmin, Admin
    public function getRiwayatJadwalByKelas(Request $request, $kelasId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jadwal/kelas/{$kelasId}/riwayat", $request->only(['tahun_ajaran', 'semester']));
    }

    // GET /akademik/jadwal/guru/{id}/riwayat — SuperAdmin, Admin
    public function getRiwayatJadwalByGuru(Request $request, $guruId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jadwal/guru/{$guruId}/riwayat", $request->only(['tahun_ajaran', 'semester']));
    }

    // ─── Pengaturan Bobot Nilai ─────────────────────────────────────────────────

    // GET /akademik/pengaturan-nilai — SuperAdmin, Admin
    public function getPengaturanNilai(Request $request)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/pengaturan-nilai", $request->only(['tahun_ajaran', 'semester']));
    }

    // POST /akademik/pengaturan-nilai — SuperAdmin, Admin
    public function storePengaturanNilai(Request $request)
    {
        try {
            $response = $this->performRequest('POST', "{$this->reqUrl}/pengaturan-nilai", $request->all());
            $decode   = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->auditLog('created', 'pengaturan_nilai', $decode['data']['idPengaturan'] ?? null, $request->only(['tahun_ajaran', 'semester', 'bobot_harian', 'bobot_uts', 'bobot_uas']));
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // PATCH /akademik/pengaturan-nilai/{id} — SuperAdmin, Admin
    public function updatePengaturanNilai(Request $request, $id)
    {
        try {
            $response = $this->performRequest('PATCH', "{$this->reqUrl}/pengaturan-nilai/{$id}", $request->all());
            $decode   = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'pengaturan_nilai', $id, $request->only(['bobot_harian', 'bobot_uts', 'bobot_uas']));
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ─── Nilai & Raport ─────────────────────────────────────────────────────────

    // POST /akademik/nilai — Admin, SuperAdmin, Guru
    // Guru: resolve guru_id dari email user yang login, inject X-Guru-Id
    public function storeNilai(Request $request)
    {
        try {
            $extraHeaders = $this->resolveGuruHeader($request);
            if (!is_array($extraHeaders)) return $extraHeaders;

            $response = $this->performRequest('POST', "{$this->reqUrl}/nilai", $request->all(), $extraHeaders);
            $decode   = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->auditLog('created', 'nilai', $decode['data']['idNilai'] ?? null, $request->only(['siswa_kelas_id', 'pengampu_mapel_id']));
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // PATCH /akademik/nilai/{id} — Admin, SuperAdmin, Guru
    public function updateNilai(Request $request, $id)
    {
        try {
            $extraHeaders = $this->resolveGuruHeader($request);
            if (!is_array($extraHeaders)) return $extraHeaders;

            $response = $this->performRequest('PATCH', "{$this->reqUrl}/nilai/{$id}", $request->all(), $extraHeaders);
            $decode   = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'nilai', $id, $request->only(['nilai_harian', 'nilai_uts', 'nilai_uas']));
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /akademik/nilai/{id} — Admin, SuperAdmin, Guru
    public function destroyNilai(Request $request, $id)
    {
        try {
            $extraHeaders = $this->resolveGuruHeader($request);
            if (!is_array($extraHeaders)) return $extraHeaders;

            $response = $this->performRequest('DELETE', "{$this->reqUrl}/nilai/{$id}", [], $extraHeaders);
            $decode   = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_ACCEPTED) {
                $this->auditLog('deleted', 'nilai', $id, []);
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/nilai/pengampu/{id} — Admin, SuperAdmin, Guru (mapel sendiri), Karyawan
    public function getNilaiByPengampu(Request $request, $pengampuId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/nilai/pengampu/{$pengampuId}");
    }

    // GET /akademik/nilai/kelas/{id} — Admin, SuperAdmin, Guru (kelas sendiri), Karyawan
    public function getNilaiByKelas(Request $request, $kelasId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/nilai/kelas/{$kelasId}", $request->only(['tahun_ajaran', 'semester']));
    }

    // GET /akademik/nilai/siswa/{id} — Admin, SuperAdmin, Guru, Karyawan
    public function getNilaiBySiswa(Request $request, $siswaId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/nilai/siswa/{$siswaId}", $request->only(['tahun_ajaran', 'semester']));
    }

    // GET /akademik/nilai/saya — Siswa (self-only); Gateway resolve siswa_id dari email
    public function getNilaiSaya(Request $request)
    {
        try {
            $siswaId = $this->resolveSiswaId($request);
            if (!is_int($siswaId)) return $siswaId;

            return $this->performRequest('GET', "{$this->reqUrl}/nilai/siswa/{$siswaId}", $request->only(['tahun_ajaran', 'semester']));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/raport/saya — Siswa (self-only)
    public function getRaportSaya(Request $request)
    {
        try {
            $siswaId = $this->resolveSiswaId($request);
            if (!is_int($siswaId)) return $siswaId;

            return $this->performRequest('GET', "{$this->reqUrl}/nilai/raport/siswa/{$siswaId}", $request->only(['tahun_ajaran', 'semester']));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/raport/siswa/{id} — Admin, SuperAdmin, Guru, Karyawan
    public function getRaportSiswa(Request $request, $siswaId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/nilai/raport/siswa/{$siswaId}", $request->only(['tahun_ajaran', 'semester']));
    }

    // GET /akademik/raport/kelas/{id} — Admin, SuperAdmin, Guru, Karyawan
    public function getRaportKelas(Request $request, $kelasId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/nilai/raport/kelas/{$kelasId}", $request->only(['tahun_ajaran', 'semester']));
    }

    // GET /akademik/nilai/ranking/saya — Siswa (self-only): posisi di kelas saja
    public function getRankingSaya(Request $request)
    {
        try {
            $siswaId = $this->resolveSiswaId($request);
            if (!is_int($siswaId)) return $siswaId;

            // Cari kelas aktif siswa ini untuk semester yang diminta
            $siswaKelasData = $this->decode(
                $this->performRequest('GET', "{$this->reqUrl}/siswa/{$siswaId}/kelas", $request->only(['tahun_ajaran', 'semester']))
            );

            $kelasId = $siswaKelasData['data'][0]['kelasId'] ?? null;
            if (!$kelasId) {
                return $this->response('Siswa belum terdaftar di kelas untuk semester ini.', Response::HTTP_NOT_FOUND);
            }

            // Kirim X-Siswa-Id agar AkademikService hanya kembalikan posisi diri sendiri
            return $this->performRequest(
                'GET',
                "{$this->reqUrl}/nilai/ranking/kelas/{$kelasId}",
                $request->only(['tahun_ajaran', 'semester']),
                ['X-Siswa-Id' => $siswaId]
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/nilai/ranking/kelas/{id} — Admin, SuperAdmin, Guru, Karyawan
    public function getRankingKelas(Request $request, $kelasId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/nilai/ranking/kelas/{$kelasId}", $request->only(['tahun_ajaran', 'semester']));
    }

    // ─── Absensi per pelajaran ──────────────────────────────────────────────────

    // GET /akademik/absensi/pelajaran/sekarang — Guru: jadwal berlangsung + daftar siswa
    public function getPelajaranSekarang(Request $request)
    {
        try {
            $header = $this->resolveGuruHeader($request);
            if (!is_array($header)) return $header;

            $response = $this->performRequest('GET', "{$this->reqUrl}/absensi/pelajaran/sekarang", [], $header);
            return $this->enrichSiswaResponse($response);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/absensi/pelajaran/{jadwal_id}/siswa — Guru (miliknya) / Admin
    public function getDaftarSiswaJadwal(Request $request, $jadwalId)
    {
        try {
            $header = $request->user()->role === 'Guru' ? $this->resolveGuruHeader($request) : [];
            if (!is_array($header)) return $header;

            $response = $this->performRequest('GET', "{$this->reqUrl}/absensi/pelajaran/{$jadwalId}/siswa", $request->only(['tanggal']), $header);
            return $this->enrichSiswaResponse($response);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /akademik/absensi/pelajaran/tandai — Guru menandai absensi siswa
    public function tandaiPelajaran(Request $request)
    {
        try {
            $header = $this->resolveGuruHeader($request);
            if (!is_array($header)) return $header;

            $response = $this->performRequest('POST', "{$this->reqUrl}/absensi/pelajaran/tandai", $request->all(), $header);
            $decode   = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'absensi_pelajaran', $request->jadwal_id, [
                    'tanggal'   => $decode['data']['tanggal'] ?? null,
                    'tersimpan' => $decode['data']['tersimpan'] ?? null,
                ]);
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ─── Wali Kelas ──────────────────────────────────────────────────────────────

    // POST /akademik/wali — tetapkan wali kelas (validasi guru_id & kelas_id cross-service)
    public function assignWali(Request $request)
    {
        try {
            if ($request->filled('guru_id')) {
                $guruData = $this->decode(
                    $this->callService($this->guruBaseUri, $this->guruSecret, 'GET', $this->guruReqUrl, ['idGuru' => $request->guru_id])
                );
                if (($guruData['resCode'] ?? null) !== Response::HTTP_OK) {
                    return $this->response('Guru tidak ditemukan atau sudah tidak aktif.', Response::HTTP_NOT_FOUND);
                }
            }
            if ($request->filled('kelas_id')) {
                $kelasData = $this->decode(
                    $this->callService($this->classBaseUri, $this->classSecret, 'GET', $this->classReqUrl, ['idKelas' => $request->kelas_id])
                );
                if (($kelasData['resCode'] ?? null) !== Response::HTTP_OK) {
                    return $this->response('Kelas tidak ditemukan.', Response::HTTP_NOT_FOUND);
                }
            }

            $response = $this->performRequest('POST', "{$this->reqUrl}/wali", $request->all());
            $decode   = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->auditLog('created', 'wali_kelas', $decode['data']['idWaliKelas'] ?? null, $request->only(['guru_id', 'kelas_id', 'tahun_ajaran', 'semester']));
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // PATCH /akademik/wali/{id} — ganti guru wali
    public function gantiWali(Request $request, $id)
    {
        try {
            if ($request->filled('guru_id')) {
                $guruData = $this->decode(
                    $this->callService($this->guruBaseUri, $this->guruSecret, 'GET', $this->guruReqUrl, ['idGuru' => $request->guru_id])
                );
                if (($guruData['resCode'] ?? null) !== Response::HTTP_OK) {
                    return $this->response('Guru tidak ditemukan atau sudah tidak aktif.', Response::HTTP_NOT_FOUND);
                }
            }

            $response = $this->performRequest('PATCH', "{$this->reqUrl}/wali/{$id}", $request->only('guru_id'));
            $decode   = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'wali_kelas', $id, ['guru_id_baru' => $request->guru_id]);
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /akademik/wali/{id} — batalkan penugasan wali
    public function removeWali(Request $request, $id)
    {
        try {
            $response = $this->performRequest('DELETE', "{$this->reqUrl}/wali/{$id}");
            $decode   = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_ACCEPTED) {
                $this->auditLog('deleted', 'wali_kelas', $id, []);
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/kelas/{kelas_id}/wali — wali aktif satu kelas
    public function getWaliByKelas(Request $request, $kelasId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/kelas/{$kelasId}/wali", $request->only(['tahun_ajaran', 'semester']));
    }

    // GET /akademik/guru/{guru_id}/wali — kelas yang diwali seorang guru
    public function getWaliByGuru(Request $request, $guruId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/guru/{$guruId}/wali", $request->only(['tahun_ajaran', 'semester']));
    }

    // ─── Absensi keluar (pulang awal / izin keluar) ──────────────────────────────

    // POST /akademik/absensi/keluar — Guru (wali kelas) / Admin menyetujui izin keluar
    public function catatKeluar(Request $request)
    {
        try {
            // Penyetuju Guru -> inject X-Guru-Id agar service verifikasi wali kelas.
            // Admin/SuperAdmin -> resolveGuruHeader kembalikan [] (tanpa X-Guru-Id, override).
            $guruHeader = $this->resolveGuruHeader($request);
            if (!is_array($guruHeader)) return $guruHeader;

            $extraHeaders = array_merge(['X-User-Id' => $request->user()->id], $guruHeader);
            $response = $this->performRequest('POST', "{$this->reqUrl}/absensi/keluar", $request->all(), $extraHeaders);
            $decode = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->auditLog('created', 'absensi_keluar', $decode['data']['idKeluar'] ?? null, [
                    'siswa_id' => $request->siswa_id,
                    'jenis'    => $request->jenis,
                ]);
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/absensi/keluar — daftar izin keluar (Guru/Admin)
    public function daftarKeluar(Request $request)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/absensi/keluar", $request->only(['tanggal', 'siswa_id']));
    }

    // ─── Rekap absensi ───────────────────────────────────────────────────────────

    private const REKAP_PARAMS = ['tahun_ajaran', 'semester', 'tanggal_dari', 'tanggal_sampai'];

    // GET /akademik/absensi/rekap/harian/kelas/{kelas_id}
    public function rekapHarianKelas(Request $request, $kelasId)
    {
        $response = $this->performRequest('GET', "{$this->reqUrl}/absensi/rekap/harian/kelas/{$kelasId}", $request->only(self::REKAP_PARAMS));
        return $this->enrichSiswaResponse($response);
    }

    // GET /akademik/absensi/rekap/harian/siswa/{siswa_id}
    public function rekapHarianSiswa(Request $request, $siswaId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/absensi/rekap/harian/siswa/{$siswaId}", $request->only(self::REKAP_PARAMS));
    }

    // GET /akademik/absensi/rekap/harian/saya — Siswa
    public function rekapHarianSaya(Request $request)
    {
        try {
            $siswaId = $this->resolveSiswaId($request);
            if (!is_int($siswaId)) return $siswaId;
            return $this->performRequest('GET', "{$this->reqUrl}/absensi/rekap/harian/siswa/{$siswaId}", $request->only(self::REKAP_PARAMS));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/absensi/rekap/pelajaran/siswa/{siswa_id}
    public function rekapPelajaranSiswa(Request $request, $siswaId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/absensi/rekap/pelajaran/siswa/{$siswaId}", $request->only(self::REKAP_PARAMS));
    }

    // GET /akademik/absensi/rekap/pelajaran/saya — Siswa
    public function rekapPelajaranSaya(Request $request)
    {
        try {
            $siswaId = $this->resolveSiswaId($request);
            if (!is_int($siswaId)) return $siswaId;
            return $this->performRequest('GET', "{$this->reqUrl}/absensi/rekap/pelajaran/siswa/{$siswaId}", $request->only(self::REKAP_PARAMS));
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /akademik/absensi/rekap/pegawai/{subjek_tipe}/{subjek_id}
    public function rekapPegawai(Request $request, $subjekTipe, $subjekId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/absensi/rekap/pegawai/{$subjekTipe}/{$subjekId}", $request->only(self::REKAP_PARAMS));
    }

    // Tambahkan namaLengkap ke tiap entri data.siswa (AkademikService hanya simpan id)
    private function enrichSiswaResponse($response)
    {
        $decode = $this->decode($response);
        if (($decode['resCode'] ?? null) !== Response::HTTP_OK || empty($decode['data']['siswa'])) {
            return $response instanceof \Illuminate\Http\Response
                ? $response
                : response($response);
        }

        $siswaResp = $this->decode(
            $this->callService($this->siswaBaseUri, $this->siswaSecret, 'GET', "{$this->siswaReqUrl}/all", ['per_page' => 9999])
        );
        $map = [];
        foreach (($siswaResp['data']['data'] ?? []) as $s) {
            $map[$s['idSiswa'] ?? null] = $s['namaLengkap'] ?? null;
        }

        $decode['data']['siswa'] = array_map(function ($row) use ($map) {
            $row['namaLengkap'] = $map[$row['siswaId']] ?? null;
            return $row;
        }, $decode['data']['siswa']);

        return $this->response($decode['resMsg'] ?? 'Ok', Response::HTTP_OK, $decode['data']);
    }

    // ─── Helper: resolve identitas dari email user yang login ───────────────────

    // Untuk Guru role: resolve guru_id dan kembalikan sebagai header array
    // Untuk Admin/SuperAdmin: kembalikan [] (tidak perlu header)
    // Kembalikan Response jika gagal
    private function resolveGuruHeader(Request $request): array|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'Guru') {
            return [];
        }

        $guruData = $this->decode(
            $this->callService($this->guruBaseUri, $this->guruSecret, 'GET', "{$this->guruReqUrl}/lookup", ['email' => $user->email])
        );

        if (($guruData['resCode'] ?? null) !== Response::HTTP_OK) {
            return $this->response('Profil guru tidak ditemukan untuk akun ini.', Response::HTTP_NOT_FOUND);
        }

        return ['X-Guru-Id' => $guruData['data']['idGuru']];
    }

    // Untuk Siswa role: resolve siswa_id dari email user yang login
    // Kembalikan siswa_id (int) atau Response jika gagal
    private function resolveSiswaId(Request $request): int|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $siswaData = $this->decode(
            $this->callService($this->siswaBaseUri, $this->siswaSecret, 'GET', "{$this->siswaReqUrl}/lookup", ['email' => $user->email])
        );

        if (($siswaData['resCode'] ?? null) !== Response::HTTP_OK) {
            return $this->response('Profil siswa tidak ditemukan untuk akun ini.', Response::HTTP_NOT_FOUND);
        }

        return (int) ($siswaData['data']['idSiswa']);
    }

    // GET /akademik/siswa/belum-terdaftar — SuperAdmin, Admin
    // Cross-service: siswa di SiswaService yang belum punya siswa_kelas untuk semester ini
    public function getSiswaBelumTerdaftar(Request $request)
    {
        try {
            $tahunAjaran = $request->input('tahun_ajaran');
            $semester    = $request->input('semester');

            if (!$tahunAjaran || !$semester) {
                $semAktif = $this->decode(
                    $this->performRequest('GET', "{$this->reqUrl}/semester/aktif")
                );
                if (($semAktif['resCode'] ?? null) !== Response::HTTP_OK) {
                    return $this->response('Semester aktif tidak ditemukan.', Response::HTTP_NOT_FOUND);
                }
                $tahunAjaran = $tahunAjaran ?: ($semAktif['data']['tahunAjaran'] ?? null);
                $semester    = $semester    ?: ($semAktif['data']['semester']    ?? null);
            }

            if (!$tahunAjaran || !$semester) {
                return $this->response('Parameter tahun_ajaran dan semester diperlukan.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Ambil siswa_id yang sudah terdaftar di kelas dari AkademikService
            $enrolledResp = $this->decode(
                $this->performRequest('GET', "{$this->reqUrl}/siswa-kelas/terdaftar", [
                    'tahun_ajaran' => $tahunAjaran,
                    'semester'     => $semester,
                ])
            );

            if (($enrolledResp['resCode'] ?? null) !== Response::HTTP_OK) {
                return $this->response('Gagal mengambil data siswa terdaftar.', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $enrolledIds = $enrolledResp['data'] ?? [];

            // Ambil semua siswa aktif dari SiswaService
            $siswaResp = $this->decode(
                $this->callService($this->siswaBaseUri, $this->siswaSecret, 'GET', "{$this->siswaReqUrl}/all", ['per_page' => 9999])
            );

            if (($siswaResp['resCode'] ?? null) !== Response::HTTP_OK) {
                return $this->response('Gagal mengambil data siswa.', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $allSiswa = $siswaResp['data']['data'] ?? [];

            $belumTerdaftar = array_values(
                array_filter($allSiswa, fn($s) => !in_array($s['idSiswa'] ?? null, $enrolledIds))
            );

            return $this->response('Siswa belum terdaftar di kelas.', Response::HTTP_OK, [
                'tahun_ajaran'    => $tahunAjaran,
                'semester'        => (int) $semester,
                'total_siswa'     => count($allSiswa),
                'total_terdaftar' => count($enrolledIds),
                'total_belum'     => count($belumTerdaftar),
                'siswa'           => $belumTerdaftar,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Panggil service lain dengan swap baseUri/secret sementara
    private function callService(string $baseUri, string $secret, string $method, string $url, array $params = [])
    {
        [$origUri, $origSecret] = [$this->baseUri, $this->secret];
        [$this->baseUri, $this->secret] = [$baseUri, $secret];
        $response = $this->performRequest($method, $url, $params);
        [$this->baseUri, $this->secret] = [$origUri, $origSecret];
        return $response;
    }

    private function decode($response): array
    {
        $raw = $response instanceof \Illuminate\Http\Response
            ? $response->getContent()
            : $response;
        return json_decode($raw, true) ?? [];
    }
}
