<?php

namespace App\Http\Controllers\Master;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Traits\ApiResponser;
use App\Traits\LogsAudit;
use App\Traits\SaringPii;
use App\Services\UserService;
use App\Http\Controllers\Controller;
use App\Traits\ConsumeMicroserviceService;

class GuruController extends Controller
{
    use ConsumeMicroserviceService, ApiResponser, LogsAudit, SaringPii;
    private $userService, $baseUri, $secret, $reqUrl;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
        $this->reqUrl = config('gateway.guru_prefix');
        $this->baseUri = config('services.guru.base_uri');
        $this->secret = config('services.guru.secret');
    }

    public function index(Request $request) {
        return $this->petakanUrlFoto(
            $this->performRequest($request->method(), "{$this->reqUrl}/all", $request->only(['page', 'per_page', 'search', 'foto']))
        );
    }

    // Field profil guru yang boleh dilihat non-pengelola (Guru, Siswa, dan
    // karyawan biasa). Data pribadi — NIK, alamat, telepon, tanggal lahir, dll. —
    // hanya untuk SuperAdmin, Admin, dan Administrator Sekolah.
    private const GURU_PUBLIC_FIELDS = [
        'idGuru', 'namaLengkap', 'nip', 'email', 'jabatan',
        'statusKepegawaian', 'pendidikanTerakhir', 'foto',
    ];

    public function show(Request $request) {
        $response = $this->performRequest($request->method(), "{$this->reqUrl}", $request->only(['idGuru']));

        // Sebelumnya kondisinya menyebut role yang DISARING (Guru, Siswa),
        // sehingga role `Karyawan` — termasuk satpam/kebersihan — lolos melihat
        // PII rekan kerjanya. Sekarang yang disebut adalah pihak yang BOLEH
        // penuh; role baru apa pun otomatis tersaring, bukan otomatis bocor.
        if (!auth()->user()->isPengelola()) {
            return $this->saringDetail($response, self::GURU_PUBLIC_FIELDS);
        }

        return $response;
    }

    public function store(Request $request) {
        try {
            // Dicek SEBELUM record domain dibuat: kalau tidak, record-nya
            // terlanjur tersimpan lalu pembuatan akun gagal (lihat
            // UserService::emailDipakai).
            if ($request->filled('email') && $this->userService->emailDipakaiAktif($request->email)) {
                return $this->response(
                    'Email sudah terpakai akun lain yang masih aktif. Pakai email berbeda.',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $response = $this->performRequest($request->method(), "{$this->reqUrl}", $request->all());
            $decode = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->userService->create($request->namaLengkap, $request->email, "Guru");
                $this->auditLog('created', 'guru', $decode['data']['idGuru'] ?? null, [
                    'namaLengkap' => $request->namaLengkap,
                    'email'       => $request->email,
                    'nip'         => $request->nip,
                    'jabatan'     => $request->jabatan,
                ]);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request) {
        try {
            $response = $this->performRequest($request->method(), "{$this->reqUrl}/update", $request->all());
            $decode = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_ACCEPTED) {
                if ($request->filled('namaLengkap')) {
                    $this->userService->update($decode['data']['email'], $request->namaLengkap);
                }
                $this->auditLog('updated', 'guru', $request->idGuru, array_filter([
                    'namaLengkap' => $request->namaLengkap,
                    'jabatan'     => $request->jabatan,
                    'alamat'      => $request->alamat,
                ]));
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request, $id) {
        try {
            // Ambil data guru sebelum hapus untuk cek role dan dapatkan email
            $getResponse = $this->performRequest('GET', $this->reqUrl, ['idGuru' => $id]);
            $getData = $this->decode($getResponse);

            if (($getData['resCode'] ?? null) !== Response::HTTP_OK) {
                return $getResponse;
            }

            $targetEmail = $getData['data']['email'] ?? null;

            // Admin tidak boleh menghapus data milik Admin/SuperAdmin lain
            if ($targetEmail && auth()->user()->role === 'Admin') {
                $targetUser = User::where('email', $targetEmail)->first();
                if ($targetUser && in_array($targetUser->role, ['Admin', 'SuperAdmin'])) {
                    return $this->response(
                        'Admin tidak dapat menghapus data milik Admin atau SuperAdmin.',
                        Response::HTTP_FORBIDDEN
                    );
                }
            }

            $response = $this->performRequest('DELETE', "{$this->reqUrl}/{$id}");
            $decode = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_ACCEPTED) {
                $email = $decode['data']['email'] ?? $targetEmail;
                if ($email) {
                    $this->userService->delete($email);
                }
                $this->auditLog('deleted', 'guru', $id, [
                    'email' => $email,
                ]);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Terbitkan/ganti kartu absensi guru
    public function terbitkanKartu(Request $request)
    {
        try {
            $response = $this->performRequest('POST', "{$this->reqUrl}/kartu/terbitkan", $request->all());
            $decode = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'guru', $request->idGuru, ['kartu' => 'diterbitkan']);
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Blokir kartu absensi guru (hilang/blokir)
    public function blokirKartu(Request $request)
    {
        try {
            $response = $this->performRequest('POST', "{$this->reqUrl}/kartu/blokir", $request->all());
            $decode = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'guru', $request->idGuru, ['kartu' => 'diblokir', 'status' => $request->input('status', 'hilang')]);
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function decode($response): array
    {
        $raw = $response instanceof \Illuminate\Http\Response
            ? $response->getContent()
            : $response;
        return json_decode($raw, true) ?? [];
    }

    /**
     * GET /{prefix}/nama — id + nama saja, termasuk entitas yang sudah dihapus.
     *
     * Dipakai klien sebagai cache resolusi id->nama. Sengaja TIDAK disaring per
     * role seperti detail: isinya hanya id + nama, tanpa PII sama sekali. Gating
     * aksesnya tetap sama dengan `/all` masing-masing modul (lihat route).
     */
    public function nama(Request $request)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/nama", $request->only(['ids']));
    }

    /**
     * Ubah penanda `punyaFoto` dari service menjadi URL foto yang bisa dipanggil klien.
     *
     * URL-nya menunjuk endpoint ber-autentikasi, BUKAN `/storage/...`: foto ada di
     * disk private dan memindahkannya ke publik akan membuat foto setiap orang bisa
     * diambil siapa pun yang menebak URL. Klien (Coil/OkHttp) mengirim header
     * Authorization seperti permintaan lain.
     */
    private function petakanUrlFoto($response)
    {
        $decode = $this->decode($response);
        if (($decode['resCode'] ?? null) !== \Illuminate\Http\Response::HTTP_OK
            || !is_array($decode['data']['data'] ?? null)) {
            return $response;
        }

        $decode['data']['data'] = array_map(function ($row) {
            if (!is_array($row) || !array_key_exists('punyaFoto', $row)) {
                return $row;
            }
            $id = $row['idGuru'] ?? null;
            $row['foto'] = ($row['punyaFoto'] && $id)
                ? '/api/' . $this->reqUrl . '/foto/' . $id
                : null;
            unset($row['punyaFoto']);
            return $row;
        }, $decode['data']['data']);

        return $this->response($decode['resMsg'] ?? 'OK', \Illuminate\Http\Response::HTTP_OK, $decode['data']);
    }

    /** GET /{prefix}/foto/{id} — teruskan berkas foto apa adanya. */
    public function foto(Request $request, $id)
    {
        return $this->performRequest('GET', "{$this->reqUrl}/foto/{$id}");
    }
}
