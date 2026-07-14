<?php

namespace App\Http\Controllers\Absensi;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Traits\ApiResponser;
use App\Http\Controllers\Controller;
use App\Traits\ConsumeMicroserviceService;

/**
 * Absensi berbasis SCAN dari terminal (bukan user login).
 * Route pakai middleware auth.terminal — terminal terverifikasi dilampirkan
 * ke request->attributes('terminal'). Gateway meresolusi kartu_uid ke subjek
 * (siswa/guru/karyawan) via prefix, memvalidasi kartu aktif, lalu memerintahkan
 * AkademikService mencatat absensi.
 */
class AbsensiController extends Controller
{
    use ConsumeMicroserviceService, ApiResponser;

    private $baseUri, $secret, $reqUrl; // akademik (target performRequest aktif)

    private $siswaBaseUri, $siswaSecret, $siswaReqUrl;
    private $guruBaseUri, $guruSecret, $guruReqUrl;
    private $karyawanBaseUri, $karyawanSecret, $karyawanReqUrl;

    public function __construct()
    {
        $this->reqUrl  = config('gateway.akademik_prefix');
        $this->baseUri = config('services.akademik.base_uri');
        $this->secret  = config('services.akademik.secret');

        $this->siswaBaseUri = config('services.siswa.base_uri');
        $this->siswaSecret  = config('services.siswa.secret');
        $this->siswaReqUrl  = config('gateway.siswa_prefix');

        $this->guruBaseUri = config('services.guru.base_uri');
        $this->guruSecret  = config('services.guru.secret');
        $this->guruReqUrl  = config('gateway.guru_prefix');

        $this->karyawanBaseUri = config('services.karyawan.base_uri');
        $this->karyawanSecret  = config('services.karyawan.secret');
        $this->karyawanReqUrl  = config('gateway.karyawan_prefix');
    }

    // POST /absensi/scan — dipanggil terminal (header X-Terminal-Id/Token)
    public function scan(Request $request)
    {
        try {
            $terminal = $request->attributes->get('terminal');
            $uid = trim((string) $request->input('kartu_uid'));

            if ($uid === '') {
                return $this->response('kartu_uid wajib diisi.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $prefix = strtoupper(substr($uid, 0, 4));

            return match ($prefix) {
                'SIS-'  => $this->scanSiswa($uid, $terminal),
                'GUR-'  => $this->scanPegawai($uid, 'guru', $terminal),
                'KAR-'  => $this->scanPegawai($uid, 'karyawan', $terminal),
                default => $this->response('Kartu tidak dikenali.', Response::HTTP_NOT_FOUND),
            };
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function scanSiswa(string $uid, $terminal)
    {
        $lookup = $this->decode(
            $this->callService($this->siswaBaseUri, $this->siswaSecret, 'GET', "{$this->siswaReqUrl}/lookup-kartu", ['uid' => $uid])
        );
        if (($lookup['resCode'] ?? null) !== Response::HTTP_OK) {
            return $this->response('Kartu tidak dikenali.', Response::HTTP_NOT_FOUND);
        }

        $status = $lookup['data']['kartuStatus'] ?? null;
        if ($status !== 'aktif') {
            return $this->response("Kartu tidak aktif ({$status}).", Response::HTTP_FORBIDDEN, ['kartuStatus' => $status]);
        }

        return $this->performRequest('POST', "{$this->reqUrl}/absensi/scan-siswa", [
            'siswa_id'    => $lookup['data']['idSiswa'],
            'terminal_id' => $terminal?->id,
            'metode'      => 'scan',
        ]);
    }

    private function scanPegawai(string $uid, string $tipe, $terminal)
    {
        [$baseUri, $secret, $reqUrl, $idKey] = $tipe === 'guru'
            ? [$this->guruBaseUri, $this->guruSecret, $this->guruReqUrl, 'idGuru']
            : [$this->karyawanBaseUri, $this->karyawanSecret, $this->karyawanReqUrl, 'idKaryawan'];

        $lookup = $this->decode(
            $this->callService($baseUri, $secret, 'GET', "{$reqUrl}/lookup-kartu", ['uid' => $uid])
        );
        if (($lookup['resCode'] ?? null) !== Response::HTTP_OK) {
            return $this->response('Kartu tidak dikenali.', Response::HTTP_NOT_FOUND);
        }

        $status = $lookup['data']['kartuStatus'] ?? null;
        if ($status !== 'aktif') {
            return $this->response("Kartu tidak aktif ({$status}).", Response::HTTP_FORBIDDEN, ['kartuStatus' => $status]);
        }

        return $this->performRequest('POST', "{$this->reqUrl}/absensi/scan-pegawai", [
            'subjek_tipe' => $tipe,
            'subjek_id'   => $lookup['data'][$idKey],
            'terminal_id' => $terminal?->id,
            'metode'      => 'scan',
        ]);
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
