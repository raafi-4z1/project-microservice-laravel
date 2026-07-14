<?php

namespace App\Http\Controllers\Absensi;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\PinWindow;
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

    // ── Jendela PIN (lupa kartu) ──────────────────────────────────────────────

    // Default durasi jendela PIN bila admin tidak menentukan (menit).
    private const DEFAULT_DURASI_PIN_MENIT = 10;

    // POST /absensi/pin/atur — pegawai (Guru/Karyawan) mengatur PIN sendiri
    public function aturPin(Request $request)
    {
        try {
            $user = $request->user();
            $tipe = $user->role === 'Guru' ? 'guru' : 'karyawan';

            [$baseUri, $secret, $reqUrl, $idKey] = $this->kredensialPegawai($tipe);

            // Resolusi id pegawai dari email akun yang login
            $lookup = $this->decode(
                $this->callService($baseUri, $secret, 'GET', "{$reqUrl}/lookup", ['email' => $user->email])
            );
            if (($lookup['resCode'] ?? null) !== Response::HTTP_OK) {
                return $this->response('Profil pegawai tidak ditemukan untuk akun ini.', Response::HTTP_NOT_FOUND);
            }

            return $this->callService($baseUri, $secret, 'POST', "{$reqUrl}/pin/set", [
                $idKey => $lookup['data'][$idKey],
                'pin'  => $request->input('pin'),
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /absensi/pin/buka — admin membuka jendela PIN untuk seorang pegawai
    public function bukaPinWindow(Request $request)
    {
        try {
            $tipe     = $request->input('subjek_tipe');
            $subjekId = (int) $request->input('subjek_id');
            if (!in_array($tipe, ['guru', 'karyawan'], true) || $subjekId < 1) {
                return $this->response('subjek_tipe (guru|karyawan) dan subjek_id wajib diisi.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Validasi pegawai ada di service terkait
            [$baseUri, $secret, $reqUrl, $idKey] = $this->kredensialPegawai($tipe);
            $cek = $this->decode(
                $this->callService($baseUri, $secret, 'GET', $reqUrl, [$idKey => $subjekId])
            );
            if (($cek['resCode'] ?? null) !== Response::HTTP_OK) {
                return $this->response('Pegawai tidak ditemukan.', Response::HTTP_NOT_FOUND);
            }

            $durasi = (int) ($request->input('durasi_menit') ?: self::DEFAULT_DURASI_PIN_MENIT);
            $durasi = max(1, min(60, $durasi));

            $now    = Carbon::now();
            $window = PinWindow::create([
                'subjek_tipe'    => $tipe,
                'subjek_id'      => $subjekId,
                'dibuka_oleh'    => $request->user()->id,
                'dibuka_at'      => $now,
                'berlaku_sampai' => $now->copy()->addMinutes($durasi),
            ]);

            return $this->response('Jendela PIN dibuka.', Response::HTTP_CREATED, [
                'idPinWindow'   => $window->id,
                'subjekTipe'    => $window->subjek_tipe,
                'subjekId'      => $window->subjek_id,
                'berlakuSampai' => $window->berlaku_sampai->toDateTimeString(),
                'durasiMenit'   => $durasi,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /absensi/pin/absen — pegawai absen via NIP+PIN di terminal (lupa kartu)
    public function absenPin(Request $request)
    {
        try {
            $terminal = $request->attributes->get('terminal');
            $tipe     = $request->input('subjek_tipe');
            $nip      = trim((string) $request->input('nip'));
            $pin      = (string) $request->input('pin');

            if (!in_array($tipe, ['guru', 'karyawan'], true) || $nip === '' || $pin === '') {
                return $this->response('subjek_tipe (guru|karyawan), nip, dan pin wajib diisi.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            [$baseUri, $secret, $reqUrl, $idKey] = $this->kredensialPegawai($tipe);

            // Verifikasi NIP + PIN ke service (pesan gagal diteruskan apa adanya)
            $verify = $this->decode(
                $this->callService($baseUri, $secret, 'POST', "{$reqUrl}/pin/verify", ['nip' => $nip, 'pin' => $pin])
            );
            if (($verify['resCode'] ?? null) !== Response::HTTP_OK) {
                return $this->response($verify['resMsg'] ?? 'Verifikasi PIN gagal.', $verify['resCode'] ?? Response::HTTP_UNAUTHORIZED);
            }

            $subjekId = (int) $verify['data'][$idKey];

            $window = PinWindow::aktif()->untuk($tipe, $subjekId)->latest('berlaku_sampai')->first();
            if (!$window) {
                return $this->response('Tidak ada jendela PIN aktif. Minta admin membuka jendela.', Response::HTTP_FORBIDDEN);
            }

            $window->update(['terpakai_at' => Carbon::now()]);

            return $this->performRequest('POST', "{$this->reqUrl}/absensi/scan-pegawai", [
                'subjek_tipe'   => $tipe,
                'subjek_id'     => $subjekId,
                'terminal_id'   => $terminal?->id,
                'pin_window_id' => $window->id,
                'metode'        => 'pin',
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // [baseUri, secret, reqUrl, idKey] untuk guru/karyawan
    private function kredensialPegawai(string $tipe): array
    {
        return $tipe === 'guru'
            ? [$this->guruBaseUri, $this->guruSecret, $this->guruReqUrl, 'idGuru']
            : [$this->karyawanBaseUri, $this->karyawanSecret, $this->karyawanReqUrl, 'idKaryawan'];
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
