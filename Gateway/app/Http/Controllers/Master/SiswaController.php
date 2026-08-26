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

class SiswaController extends Controller
{
    use ConsumeMicroserviceService, ApiResponser, LogsAudit, SaringPii;
    private $userService, $baseUri, $secret, $reqUrl;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
        $this->reqUrl = config('gateway.siswa_prefix');
        $this->baseUri = config('services.siswa.base_uri');
        $this->secret = config('services.siswa.secret');
    }

    // Info publik siswa: cukup untuk direktori sekolah (siapa, kelas berapa,
    // masih aktif atau tidak) tanpa membuka jati diri. NISN, tempat/tanggal
    // lahir, alamat, telepon, dan seluruh data orang tua sengaja TIDAK ada di
    // sini — sesama siswa tidak berkepentingan atas itu.
    //
    // `foto` hanya muncul di detail; daftar `siswa/all` memang tidak memuatnya
    // karena accessor foto mengubah path menjadi Base64 dan akan membuat satu
    // halaman daftar membengkak.
    private const SISWA_PUBLIC_FIELDS = [
        'idSiswa', 'namaLengkap', 'jenisKelamin', 'status', 'foto',
    ];

    public function index(Request $request)
    {
        $bolehPii = auth()->user()->bolehLihatPiiSiswa();

        // Whitelist parameter DULU, baru tambahkan flag — supaya klien tidak bisa
        // menitipkan `search_publik` sendiri untuk membuka kembali pencarian NISN.
        $params = $request->only(['page', 'per_page', 'search']);
        if (!$bolehPii) {
            // Membuang kolom nisn dari respons saja tidak cukup: selama NISN masih
            // bisa dipakai sebagai kata kunci, jumlah baris yang cocok membocorkan
            // NISN itu sendiri digit demi digit. Lihat SiswaService::index.
            $params['search_publik'] = '1';
        }

        $response = $this->performRequest($request->method(), "{$this->reqUrl}/all", $params);

        // Siswa boleh melihat direktori teman satu sekolah, tapi versi publik.
        // Sebelumnya daftar ini dibalas apa adanya — termasuk NISN dan tanggal
        // lahir seluruh siswa — kepada siapa pun yang sudah login.
        if (!$bolehPii) {
            return $this->saringDaftar($response, self::SISWA_PUBLIC_FIELDS);
        }

        return $response;
    }

    public function show(Request $request)
    {
        $response = $this->performRequest($request->method(), "{$this->reqUrl}", $request->only(['idSiswa']));

        if (!auth()->user()->bolehLihatPiiSiswa()) {
            return $this->saringDetail($response, self::SISWA_PUBLIC_FIELDS);
        }

        return $response;
    }

    // GET /siswa/saya — role Siswa: profil DIRI SENDIRI (termasuk foto).
    // Siswa diblokir dari /siswa?idSiswa= (privasi antar-siswa), sehingga tanpa
    // endpoint ini ia tidak punya cara mengambil fotonya sendiri.
    // idSiswa diresolve dari email token, bukan dari input klien.
    public function saya(Request $request)
    {
        try {
            $lookup = $this->decode(
                $this->performRequest('GET', "{$this->reqUrl}/lookup", ['email' => $request->user()->email])
            );

            if (($lookup['resCode'] ?? null) !== Response::HTTP_OK) {
                return $this->response('Profil siswa tidak ditemukan untuk akun ini.', Response::HTTP_NOT_FOUND);
            }

            return $this->performRequest('GET', "{$this->reqUrl}", ['idSiswa' => $lookup['data']['idSiswa']]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        try {
            $response = $this->performRequest($request->method(), "{$this->reqUrl}", $request->all());
            $decode = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->userService->create($request->namaLengkap, $request->email, "Siswa");
                $this->auditLog('created', 'siswa', $decode['data']['idSiswa'] ?? null, [
                    'namaLengkap' => $request->namaLengkap,
                    'email'       => $request->email,
                    'nisn'        => $request->nisn,
                ]);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request)
    {
        try {
            $response = $this->performRequest($request->method(), "{$this->reqUrl}/update", $request->all());
            $decode = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_ACCEPTED) {
                if ($request->filled('namaLengkap')) {
                    $this->userService->update($decode['data']['email'], $request->namaLengkap);
                }
                $this->auditLog('updated', 'siswa', $request->idSiswa, array_filter([
                    'namaLengkap' => $request->namaLengkap,
                    'status'      => $request->status,
                    'statusDate'  => $request->statusDate,
                    'alamat'      => $request->alamat,
                ]));
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            // Ambil data siswa sebelum hapus untuk cek role dan dapatkan email
            $getResponse = $this->performRequest('GET', $this->reqUrl, ['idSiswa' => $id]);
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
                $this->auditLog('deleted', 'siswa', $id, [
                    'email' => $email,
                ]);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Terbitkan/ganti kartu absensi siswa
    public function terbitkanKartu(Request $request)
    {
        try {
            $response = $this->performRequest('POST', "{$this->reqUrl}/kartu/terbitkan", $request->all());
            $decode = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'siswa', $request->idSiswa, ['kartu' => 'diterbitkan']);
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Blokir kartu absensi siswa (hilang/blokir)
    public function blokirKartu(Request $request)
    {
        try {
            $response = $this->performRequest('POST', "{$this->reqUrl}/kartu/blokir", $request->all());
            $decode = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'siswa', $request->idSiswa, ['kartu' => 'diblokir', 'status' => $request->input('status', 'hilang')]);
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
}
