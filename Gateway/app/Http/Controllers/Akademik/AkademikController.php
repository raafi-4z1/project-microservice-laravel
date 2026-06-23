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

    // Untuk memanggil ClassMicroservices saat assign siswa
    private $classBaseUri, $classSecret, $classReqUrl;

    public function __construct()
    {
        $this->reqUrl       = config('gateway.akademik_prefix');
        $this->baseUri      = config('services.akademik.base_uri');
        $this->secret       = config('services.akademik.secret');

        $this->classBaseUri = config('services.class.base_uri');
        $this->classSecret  = config('services.class.secret');
        $this->classReqUrl  = config('gateway.class_prefix');
    }

    // POST /akademik/kelas/assign — SuperAdmin, Admin
    // Ambil limit_siswa dari ClassMicroservices, lalu teruskan ke AkademikService
    public function assignSiswa(Request $request)
    {
        try {
            if (!$request->filled('kelas_id')) {
                return $this->response('Field kelas_id wajib diisi.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Ambil info kelas untuk mendapatkan limit_siswa
            $kelasResponse = $this->callClassService('GET', $this->classReqUrl, ['idKelas' => $request->kelas_id]);
            $kelasData     = $this->decode($kelasResponse);

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

            $kelasResponse = $this->callClassService('GET', $this->classReqUrl, ['idKelas' => $request->kelas_id]);
            $kelasData     = $this->decode($kelasResponse);

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
    public function assignGuru(Request $request)
    {
        try {
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
    public function gantiGuru(Request $request, $id)
    {
        try {
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

    // Panggil ClassMicroservices dengan swap baseUri/secret sementara
    private function callClassService(string $method, string $url, array $params = [])
    {
        [$uri, $secret] = [$this->baseUri, $this->secret];
        $this->baseUri  = $this->classBaseUri;
        $this->secret   = $this->classSecret;
        $response = $this->performRequest($method, $url, $params);
        $this->baseUri  = $uri;
        $this->secret   = $secret;
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
