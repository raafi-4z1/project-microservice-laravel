<?php

namespace App\Http\Controllers\Master;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Traits\ApiResponser;
use App\Traits\LogsAudit;
use App\Services\UserService;
use App\Http\Controllers\Controller;
use App\Traits\ConsumeMicroserviceService;

class KaryawanController extends Controller
{
    use ConsumeMicroserviceService, ApiResponser, LogsAudit;
    private $userService, $baseUri, $secret, $reqUrl;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
        $this->reqUrl = config('gateway.karyawan_prefix');
        $this->baseUri = config('services.karyawan.base_uri');
        $this->secret = config('services.karyawan.secret');
    }

    public function index(Request $request) {
        return $this->performRequest($request->method(), "{$this->reqUrl}/all", $request->only(['page', 'per_page', 'search']));
    }

    // Field profil karyawan yang boleh dilihat role non-administratif (Guru, Siswa).
    // Data pribadi (alamat, no_telp) hanya untuk SuperAdmin, Admin, Karyawan.
    private const KARYAWAN_PUBLIC_FIELDS = [
        'idKaryawan', 'namaLengkap', 'nip', 'email', 'jabatan',
        'statusKepegawaian', 'foto',
    ];

    public function show(Request $request) {
        $response = $this->performRequest($request->method(), "{$this->reqUrl}", $request->only(['idKaryawan']));

        if (in_array(auth()->user()->role, ['Guru', 'Siswa'])) {
            $decode = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_OK && is_array($decode['data'] ?? null)) {
                return $this->response(
                    $decode['resMsg'] ?? 'OK',
                    Response::HTTP_OK,
                    array_intersect_key($decode['data'], array_flip(self::KARYAWAN_PUBLIC_FIELDS))
                );
            }
        }

        return $response;
    }

    public function store(Request $request) {
        try {
            $response = $this->performRequest($request->method(), "{$this->reqUrl}", $request->all());
            $decode = $this->decode($response);

            if (($decode['resCode'] ?? null) === Response::HTTP_CREATED) {
                $this->userService->create($request->namaLengkap, $request->email, "Karyawan");
                $this->auditLog('created', 'karyawan', $decode['data']['idKaryawan'] ?? null, [
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
                $this->auditLog('updated', 'karyawan', $request->idKaryawan, array_filter([
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
            // Ambil data karyawan sebelum hapus untuk cek role dan dapatkan email
            $getResponse = $this->performRequest('GET', $this->reqUrl, ['idKaryawan' => $id]);
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
                $this->auditLog('deleted', 'karyawan', $id, [
                    'email' => $email,
                ]);
            }

            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Terbitkan/ganti kartu absensi karyawan
    public function terbitkanKartu(Request $request)
    {
        try {
            $response = $this->performRequest('POST', "{$this->reqUrl}/kartu/terbitkan", $request->all());
            $decode = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'karyawan', $request->idKaryawan, ['kartu' => 'diterbitkan']);
            }
            return $response;
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Blokir kartu absensi karyawan (hilang/blokir)
    public function blokirKartu(Request $request)
    {
        try {
            $response = $this->performRequest('POST', "{$this->reqUrl}/kartu/blokir", $request->all());
            $decode = $this->decode($response);
            if (($decode['resCode'] ?? null) === Response::HTTP_OK) {
                $this->auditLog('updated', 'karyawan', $request->idKaryawan, ['kartu' => 'diblokir', 'status' => $request->input('status', 'hilang')]);
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
