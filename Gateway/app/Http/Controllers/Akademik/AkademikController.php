<?php

namespace App\Http\Controllers\Akademik;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
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
    private $karyawanBaseUri, $karyawanSecret, $karyawanReqUrl;

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

        $this->karyawanBaseUri = config('services.karyawan.base_uri');
        $this->karyawanSecret  = config('services.karyawan.secret');
        $this->karyawanReqUrl  = config('gateway.karyawan_prefix');
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

    // GET /akademik/jam — semua role.
    // ?tanggal= -> set jam EFEKTIF pada tanggal itu (ikut periode khusus & hari)
    public function getJamPelajaran(Request $request)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jam", $request->only(['tanggal', 'hari', 'periode_id']));
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
    public function getJadwalByPengampu(Request $request, $pengampuId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jadwal/pengampu/{$pengampuId}", $request->only(['tanggal']));
    }

    // GET /akademik/jadwal/kelas/{id} — semua role
    public function getJadwalByKelas(Request $request, $kelasId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jadwal/kelas/{$kelasId}", $request->only(['tahun_ajaran', 'semester', 'tanggal']));
    }

    // GET /akademik/jadwal/guru/{id} — semua role
    public function getJadwalByGuru(Request $request, $guruId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jadwal/guru/{$guruId}", $request->only(['tahun_ajaran', 'semester', 'tanggal']));
    }

    // GET /akademik/jadwal/siswa/{id} — semua role
    public function getJadwalBySiswa(Request $request, $siswaId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jadwal/siswa/{$siswaId}", $request->only(['tahun_ajaran', 'semester', 'tanggal']));
    }

    // GET /akademik/jadwal/pengampu/{id}/riwayat — SuperAdmin, Admin
    public function getRiwayatJadwalByPengampu(Request $request, $pengampuId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jadwal/pengampu/{$pengampuId}/riwayat", $request->only(['tanggal']));
    }

    // GET /akademik/jadwal/kelas/{id}/riwayat — SuperAdmin, Admin
    public function getRiwayatJadwalByKelas(Request $request, $kelasId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jadwal/kelas/{$kelasId}/riwayat", $request->only(['tahun_ajaran', 'semester', 'tanggal']));
    }

    // GET /akademik/jadwal/guru/{id}/riwayat — SuperAdmin, Admin
    public function getRiwayatJadwalByGuru(Request $request, $guruId)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/jadwal/guru/{$guruId}/riwayat", $request->only(['tahun_ajaran', 'semester', 'tanggal']));
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
                $this->auditLog('updated', 'nilai', $id, $request->only([
                    'nilai_harian_1', 'nilai_harian_2', 'nilai_harian_3',
                    'nilai_harian_4', 'nilai_harian_5',
                    'nilai_harian', 'nilai_uts', 'nilai_uas',
                ]));
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
        $header = $this->resolveGuruHeader($request);
        if (!is_array($header)) return $header;
        return $this->performRequest('GET', "{$this->reqUrl}/nilai/pengampu/{$pengampuId}", [], $header);
    }

    // GET /akademik/nilai/kelas/{id} — Admin, SuperAdmin, Guru (kelas sendiri), Karyawan
    public function getNilaiByKelas(Request $request, $kelasId)
    {
        $header = $this->resolveGuruHeader($request);
        if (!is_array($header)) return $header;
        return $this->performRequest('GET', "{$this->reqUrl}/nilai/kelas/{$kelasId}", $request->only(['tahun_ajaran', 'semester']), $header);
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
        $header = $this->resolveGuruHeader($request);
        if (!is_array($header)) return $header;
        return $this->performRequest('GET', "{$this->reqUrl}/nilai/raport/kelas/{$kelasId}", $request->only(['tahun_ajaran', 'semester']), $header);
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
        $header = $this->resolveGuruHeader($request);
        if (!is_array($header)) return $header;
        return $this->performRequest('GET', "{$this->reqUrl}/nilai/ranking/kelas/{$kelasId}", $request->only(['tahun_ajaran', 'semester']), $header);
    }

    /**
     * GET /akademik/nilai/ranking/angkatan/export?format=csv|pdf&...
     * SuperAdmin, Admin, Administrator Sekolah.
     *
     * Memakai perhitungan yang SAMA dengan getRankingAngkatan (dipanggil ulang
     * secara internal) supaya angka di layar dan di file tidak pernah berbeda.
     */
    public function exportRankingAngkatan(Request $request)
    {
        try {
            $format = strtolower((string) $request->input('format', 'csv'));
            if (!in_array($format, ['csv', 'pdf'], true)) {
                return $this->response('format harus csv atau pdf.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // detail=1 selalu, supaya file memuat rincian per mapel bila diminta
            $hasil = $this->decode($this->getRankingAngkatan($request));
            if (($hasil['resCode'] ?? null) !== Response::HTTP_OK) {
                return response()->json($hasil, $hasil['resCode'] ?? Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $d       = $hasil['data'];
            $jurusan = $d['jurusan'] ?: 'SEMUA';
            $namaFile = sprintf(
                'peringkat-angkatan-%s-%s-%s-sem%s',
                $this->romawiTingkat($d['tingkat']),
                $jurusan,
                str_replace('/', '-', (string) $d['tahunAjaran']),
                $d['semester']
            );

            return $format === 'pdf'
                ? $this->exportPdf($d, $namaFile)
                : $this->exportCsv($d, $namaFile);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function romawiTingkat($tingkat): string
    {
        return [1 => 'X', 2 => 'XI', 3 => 'XII'][(int) $tingkat] ?? (string) $tingkat;
    }

    private function exportCsv(array $d, string $namaFile)
    {
        $baris = [];
        $baris[] = ['Laporan Peringkat Se-Angkatan'];
        $baris[] = ['Tingkat', $this->romawiTingkat($d['tingkat'])];
        $baris[] = ['Jurusan', $d['jurusan'] ?: 'Semua jurusan'];
        $baris[] = ['Tahun Ajaran', $d['tahunAjaran']];
        $baris[] = ['Semester', $d['semester']];
        $baris[] = ['Rata-rata Angkatan', $d['rataRataAngkatan']];
        $baris[] = ['Jumlah Siswa', $d['totalSiswa']];
        $baris[] = [];
        $baris[] = ['Peringkat', 'NISN', 'Nama', 'Kelas', 'Rata-rata', 'Predikat', 'Mapel Dinilai', 'Belum Dinilai'];

        foreach ($d['ranking'] as $r) {
            $baris[] = [
                $r['peringkat'], $r['nisn'], $r['namaLengkap'], $r['kelas'],
                $r['rataRata'], $r['predikat'],
                ($r['jumlahMapel'] ?? 0) - ($r['belumDinilai'] ?? 0),
                $r['belumDinilai'] ?? 0,
            ];
        }

        $out = fopen('php://temp', 'r+');
        // BOM UTF-8 supaya Excel membaca huruf beraksen dengan benar
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($baris as $b) {
            fputcsv($out, $b, ';');   // titik koma: default Excel lokal ID
        }
        rewind($out);
        $isi = stream_get_contents($out);
        fclose($out);

        return response($isi, Response::HTTP_OK, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$namaFile}.csv\"",
        ]);
    }

    private function exportPdf(array $d, string $namaFile)
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('laporan.ranking-angkatan', [
            'd'       => $d,
            'tingkat' => $this->romawiTingkat($d['tingkat']),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("{$namaFile}.pdf");
    }

    // Skala predikat (default sekolah). Ambang bawah -> huruf, urut menurun.
    private const SKALA_PREDIKAT = [90 => 'A', 80 => 'B', 70 => 'C', 60 => 'D', 0 => 'E'];

    private function predikat(float $nilai): string
    {
        foreach (self::SKALA_PREDIKAT as $min => $huruf) {
            if ($nilai >= $min) return $huruf;
        }
        return 'E';
    }

    /**
     * GET /akademik/nilai/ranking/angkatan — SuperAdmin, Admin
     *   ?tingkat=3&jurusan=MIPA&tahun_ajaran=..&semester=..&detail=0|1
     *
     * Laporan peringkat satu angkatan (satu tingkat pada TA berjalan), opsional
     * dipersempit per jurusan. Orkestrasi lintas service:
     *   1. ClassService  : tingkat(+jurusan) -> daftar kelas
     *   2. AkademikService: rata-rata per siswa (mapel belum dinilai = 0)
     *   3. SiswaService  : nama + status (batch by-ids)
     * Peringkat dihitung DI SINI karena aturan seri memakai urutan alfabet nama,
     * dan nama hanya ada setelah langkah 3.
     */
    public function getRankingAngkatan(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'tingkat'      => 'required|in:1,2,3',
                'jurusan'      => 'sometimes|string|max:20',
                'tahun_ajaran' => ['sometimes', 'regex:/^\d{4}\/\d{4}$/'],
                'semester'     => 'sometimes|in:1,2',
                'detail'       => 'sometimes|in:0,1',
            ]);
            if ($validate->fails()) {
                return $this->response($validate->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY, $validate->errors());
            }

            // Periode: pakai yang diminta, atau semester aktif
            $ta  = $request->input('tahun_ajaran');
            $sem = $request->input('semester');
            if (!$ta || !$sem) {
                $aktif = $this->decode($this->performRequest('GET', "{$this->reqUrl}/semester/aktif"));
                if (($aktif['resCode'] ?? null) !== Response::HTTP_OK) {
                    return $this->response('Belum ada semester aktif; kirim tahun_ajaran & semester secara eksplisit.', Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $ta  = $ta  ?: $aktif['data']['tahunAjaran'];
                $sem = $sem ?: $aktif['data']['semester'];
            }
            $detail = (string) $request->input('detail', '0') === '1';

            // 1. Kelas pada tingkat (+jurusan) ini
            $paramKelas = ['tingkat' => $request->tingkat, 'per_page' => 200];
            if ($request->filled('jurusan')) {
                $paramKelas['jurusan'] = strtoupper($request->jurusan);
            }
            $kelasResp = $this->decode(
                $this->callService($this->classBaseUri, $this->classSecret, 'GET', "{$this->classReqUrl}/all", $paramKelas)
            );
            $kelasRows = $kelasResp['data']['data'] ?? [];
            if (empty($kelasRows)) {
                return $this->response('Tidak ada kelas pada tingkat/jurusan tersebut.', Response::HTTP_NOT_FOUND);
            }
            $namaKelas = [];
            foreach ($kelasRows as $k) {
                $namaKelas[(int) $k['idKelas']] = $k['namaKelas'] ?? null;
            }

            // 2. Rata-rata per siswa dari AkademikService
            $rekap = $this->decode($this->performRequest('GET', "{$this->reqUrl}/nilai/angkatan/rekap", [
                'kelas_ids'    => implode(',', array_keys($namaKelas)),
                'tahun_ajaran' => $ta,
                'semester'     => $sem,
                'detail'       => $detail ? 1 : 0,
            ]));
            if (($rekap['resCode'] ?? null) !== Response::HTTP_OK) {
                return $this->response($rekap['resMsg'] ?? 'Gagal mengambil rekap nilai.', $rekap['resCode'] ?? Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            $baris = $rekap['data']['siswa'] ?? [];
            if (empty($baris)) {
                return $this->response('Belum ada siswa terdaftar pada angkatan tersebut.', Response::HTTP_OK, [
                    'tingkat' => (int) $request->tingkat,
                    'jurusan' => $request->filled('jurusan') ? strtoupper($request->jurusan) : null,
                    'tahunAjaran' => $ta, 'semester' => (int) $sem,
                    'rataRataAngkatan' => null, 'totalSiswa' => 0, 'ranking' => [],
                ]);
            }

            // 3. Nama + status siswa (batch)
            $ids = array_values(array_unique(array_column($baris, 'siswaId')));
            $siswaResp = $this->decode(
                $this->callService($this->siswaBaseUri, $this->siswaSecret, 'POST', "{$this->siswaReqUrl}/by-ids", ['ids' => $ids])
            );
            $infoSiswa = [];
            foreach (($siswaResp['data'] ?? []) as $s) {
                $infoSiswa[(int) $s['idSiswa']] = $s;
            }

            // Buang siswa non-aktif (Lulus/Berhenti/Pindah) SEBELUM peringkat dihitung
            $peserta = [];
            foreach ($baris as $b) {
                $info = $infoSiswa[(int) $b['siswaId']] ?? null;
                if (!$info || ($info['status'] ?? 'Aktif') !== 'Aktif') {
                    continue;
                }
                $row = [
                    'siswaId'      => (int) $b['siswaId'],
                    'namaLengkap'  => $info['namaLengkap'] ?? null,
                    'nisn'         => $info['nisn'] ?? null,
                    'kelas'        => $namaKelas[(int) $b['kelasId']] ?? null,
                    'rataRata'     => (float) $b['rataRata'],
                    'predikat'     => $this->predikat((float) $b['rataRata']),
                    'jumlahMapel'  => $b['jumlahMapel'] ?? null,
                    'belumDinilai' => $b['belumDinilai'] ?? null,
                ];
                if ($detail && isset($b['nilai'])) {
                    $row['nilai'] = array_map(fn($n) => $n + [
                        'predikat' => $this->predikat((float) $n['nilaiAkhir']),
                    ], $b['nilai']);
                }
                $peserta[] = $row;
            }

            if (empty($peserta)) {
                return $this->response('Tidak ada siswa aktif pada angkatan tersebut.', Response::HTTP_OK, [
                    'tingkat' => (int) $request->tingkat,
                    'jurusan' => $request->filled('jurusan') ? strtoupper($request->jurusan) : null,
                    'tahunAjaran' => $ta, 'semester' => (int) $sem,
                    'rataRataAngkatan' => null, 'totalSiswa' => 0, 'ranking' => [],
                ]);
            }

            // Urut: rata-rata menurun, seri -> alfabet nama
            usort($peserta, function ($a, $b) {
                return $b['rataRata'] <=> $a['rataRata']
                    ?: strcasecmp((string) $a['namaLengkap'], (string) $b['namaLengkap']);
            });

            // Peringkat kompetisi standar: seri = peringkat sama, berikutnya melompat
            $peringkat = 0;
            $sebelum   = null;
            foreach ($peserta as $i => &$p) {
                if ($sebelum === null || $p['rataRata'] < $sebelum) {
                    $peringkat = $i + 1;
                    $sebelum   = $p['rataRata'];
                }
                $p = ['peringkat' => $peringkat] + $p;
            }
            unset($p);

            $rataAngkatan = round(array_sum(array_column($peserta, 'rataRata')) / count($peserta), 2);

            return $this->response('Peringkat se-angkatan.', Response::HTTP_OK, [
                'tingkat'          => (int) $request->tingkat,
                'jurusan'          => $request->filled('jurusan') ? strtoupper($request->jurusan) : null,
                'tahunAjaran'      => $ta,
                'semester'         => (int) $sem,
                'rataRataAngkatan' => $rataAngkatan,
                'totalSiswa'       => count($peserta),
                'ranking'          => $peserta,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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

    // ─── Pengaturan Absensi (ambang terlambat, durasi PIN) ───────────────────────

    // GET /akademik/pengaturan-absensi/efektif?tanggal= — aturan yang berlaku pada tanggal
    public function getPengaturanAbsensiEfektif(Request $request)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/pengaturan-absensi/efektif", $request->only(['tanggal', 'tahun_ajaran', 'semester']));
    }

    // GET /akademik/pengaturan-absensi — SuperAdmin, Admin
    public function getPengaturanAbsensi(Request $request)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/pengaturan-absensi", $request->only(['tahun_ajaran', 'semester']));
    }

    // POST /akademik/pengaturan-absensi — SuperAdmin, Admin
    public function storePengaturanAbsensi(Request $request)
    {
        try {
            $response = $this->performRequest('POST', "{$this->reqUrl}/pengaturan-absensi", $request->all());
            $decode   = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->auditLog('created', 'pengaturan_absensi', $decode['data']['idPengaturanAbsensi'] ?? null,
                    $request->only(['tahun_ajaran', 'semester', 'periode_id', 'batas_terlambat_siswa', 'batas_terlambat_pegawai']));
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // PATCH /akademik/pengaturan-absensi/{id} — SuperAdmin, Admin
    public function updatePengaturanAbsensi(Request $request, $id)
    {
        try {
            $response = $this->performRequest('PATCH', "{$this->reqUrl}/pengaturan-absensi/{$id}", $request->all());
            $decode   = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'pengaturan_absensi', $id,
                    $request->only(['batas_terlambat_siswa', 'batas_terlambat_pegawai', 'durasi_pin_window_menit']));
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /akademik/pengaturan-absensi/{id} — SuperAdmin, Admin
    public function destroyPengaturanAbsensi($id)
    {
        try {
            $response = $this->performRequest('DELETE', "{$this->reqUrl}/pengaturan-absensi/{$id}");
            $decode   = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_ACCEPTED) {
                $this->auditLog('deleted', 'pengaturan_absensi', $id, []);
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ─── Periode Khusus (Ramadan / ujian / libur / kegiatan) ─────────────────────

    // GET /akademik/periode — daftar periode (filter: tahun_ajaran, semester, jenis)
    public function getPeriode(Request $request)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/periode", $request->only(['tahun_ajaran', 'semester', 'jenis']));
    }

    // GET /akademik/periode/aktif?tanggal= — periode yang berlaku pada tanggal
    public function getPeriodeAktif(Request $request)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/periode/aktif", $request->only(['tanggal']));
    }

    // POST /akademik/periode — SuperAdmin, Admin
    public function storePeriode(Request $request)
    {
        try {
            $response = $this->performRequest('POST', "{$this->reqUrl}/periode", $request->all());
            $decode   = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->auditLog('created', 'periode_khusus', $decode['data']['idPeriode'] ?? null,
                    $request->only(['nama', 'jenis', 'berlaku_dari', 'berlaku_sampai']));
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // PATCH /akademik/periode/{id} — SuperAdmin, Admin
    public function updatePeriode(Request $request, $id)
    {
        try {
            $response = $this->performRequest('PATCH', "{$this->reqUrl}/periode/{$id}", $request->all());
            $decode   = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'periode_khusus', $id,
                    $request->only(['nama', 'jenis', 'berlaku_dari', 'berlaku_sampai', 'kbm_normal']));
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /akademik/periode/{id} — SuperAdmin, Admin
    public function destroyPeriode($id)
    {
        try {
            $response = $this->performRequest('DELETE', "{$this->reqUrl}/periode/{$id}");
            $decode   = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_ACCEPTED) {
                $this->auditLog('deleted', 'periode_khusus', $id, []);
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
        // X-Guru-Id membuat AkademikService memfilter ke siswa di kelas yang guru
        // ini WALI-i (hanya wali yang boleh menyetujui izin keluar).
        $header = $this->resolveGuruHeader($request);
        if (!is_array($header)) return $header;
        return $this->performRequest('GET', "{$this->reqUrl}/absensi/keluar", $request->only(['tanggal', 'siswa_id']), $header);
    }

    // ─── Rekap absensi ───────────────────────────────────────────────────────────

    private const REKAP_PARAMS = ['tahun_ajaran', 'semester', 'tanggal_dari', 'tanggal_sampai'];

    // GET /akademik/absensi/rekap/harian/kelas/{kelas_id}
    public function rekapHarianKelas(Request $request, $kelasId)
    {
        $header = $this->resolveGuruHeader($request);
        if (!is_array($header)) return $header;
        $response = $this->performRequest('GET', "{$this->reqUrl}/absensi/rekap/harian/kelas/{$kelasId}", $request->only(self::REKAP_PARAMS), $header);
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

    // GET /akademik/absensi/rekap/pegawai/saya — Guru, Karyawan (dan Admin yang
    // juga terdaftar sebagai pegawai). Subjek diresolve dari email token, jadi
    // pemanggil hanya bisa melihat rekap DIRINYA sendiri; id domain tidak perlu
    // diketahui klien. Bentuk respons identik dengan rekap/pegawai/{tipe}/{id}.
    public function rekapPegawaiSaya(Request $request)
    {
        try {
            $subjek = $this->resolvePegawaiSaya($request);
            if (!is_array($subjek)) return $subjek;

            return $this->performRequest(
                'GET',
                "{$this->reqUrl}/absensi/rekap/pegawai/{$subjek['tipe']}/{$subjek['id']}",
                $request->only(self::REKAP_PARAMS)
            );
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Resolve pemilik token menjadi pegawai: coba GuruService dulu, lalu
     * KaryawanService. Kembalikan ['tipe' => 'guru'|'karyawan', 'id' => int]
     * atau JsonResponse 404 kalau akun ini bukan pegawai.
     */
    private function resolvePegawaiSaya(Request $request): array|\Illuminate\Http\JsonResponse
    {
        $email = $request->user()->email;

        $guru = $this->decode(
            $this->callService($this->guruBaseUri, $this->guruSecret, 'GET', "{$this->guruReqUrl}/lookup", ['email' => $email])
        );
        if (($guru['resCode'] ?? null) === Response::HTTP_OK) {
            return ['tipe' => 'guru', 'id' => (int) $guru['data']['idGuru']];
        }

        $karyawan = $this->decode(
            $this->callService($this->karyawanBaseUri, $this->karyawanSecret, 'GET', "{$this->karyawanReqUrl}/lookup", ['email' => $email])
        );
        if (($karyawan['resCode'] ?? null) === Response::HTTP_OK) {
            return ['tipe' => 'karyawan', 'id' => (int) $karyawan['data']['idKaryawan']];
        }

        return $this->response(
            'Akun ini tidak terhubung ke data guru maupun karyawan.',
            Response::HTTP_NOT_FOUND
        );
    }

    // Tambahkan namaLengkap ke tiap entri data.siswa (AkademikService hanya simpan id).
    // Hanya id yang ada di respons yang di-lookup (batch) — jangan tarik seluruh
    // tabel siswa hanya untuk mencari beberapa puluh nama.
    private function enrichSiswaResponse($response)
    {
        $decode = $this->decode($response);
        if (($decode['resCode'] ?? null) !== Response::HTTP_OK || empty($decode['data']['siswa'])) {
            return $response instanceof \Illuminate\Http\Response
                ? $response
                : response($response);
        }

        $ids = array_values(array_unique(array_filter(
            array_column($decode['data']['siswa'], 'siswaId')
        )));
        if (empty($ids)) {
            return $this->response($decode['resMsg'] ?? 'Ok', Response::HTTP_OK, $decode['data']);
        }

        $siswaResp = $this->decode(
            $this->callService($this->siswaBaseUri, $this->siswaSecret, 'POST', "{$this->siswaReqUrl}/by-ids", ['ids' => $ids])
        );
        $map = [];
        foreach (($siswaResp['data'] ?? []) as $s) {
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

            // Ambil semua siswa aktif dari SiswaService.
            // SENGAJA memakai /all (bukan /by-ids seperti enrichSiswaResponse): fitur ini
            // mencari siswa yang BELUM terdaftar = seluruh siswa MINUS yang terdaftar,
            // jadi daftar lengkap memang dibutuhkan dan id targetnya belum diketahui.
            // Endpoint admin, jarang dipakai (saat pembagian kelas per semester).
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
        // Pakai tipe dasar Symfony, bukan Illuminate\Http\Response: respons
        // internal dari $this->response() adalah JsonResponse (turunan lain),
        // dan kalau tidak ikut tertangkap json_decode menerima objek -> [].
        $raw = $response instanceof \Symfony\Component\HttpFoundation\Response
            ? $response->getContent()
            : $response;
        return json_decode($raw, true) ?? [];
    }
}
