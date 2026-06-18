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

class SiswaController extends Controller
{
    use ConsumeMicroserviceService, ApiResponser, LogsAudit;
    private $userService, $baseUri, $secret, $reqUrl;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
        $this->reqUrl = config('gateway.siswa_prefix');
        $this->baseUri = config('services.siswa.base_uri');
        $this->secret = config('services.siswa.secret');
    }

    public function index(Request $request)
    {
        return $this->performRequest($request->method(), "{$this->reqUrl}/all", $request->only(['page', 'per_page']));
    }

    public function show(Request $request)
    {
        return $this->performRequest($request->method(), "{$this->reqUrl}", $request->only(['idSiswa']));
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

    private function decode($response): array
    {
        $raw = $response instanceof \Illuminate\Http\Response
            ? $response->getContent()
            : $response;
        return json_decode($raw, true) ?? [];
    }
}
