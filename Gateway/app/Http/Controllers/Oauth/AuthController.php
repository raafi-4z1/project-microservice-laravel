<?php

namespace App\Http\Controllers\Oauth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Traits\ApiResponser;
use App\Traits\LogsAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use ApiResponser, LogsAudit;

    // Peran yang boleh dibuat lewat API
    private const REGISTERABLE_ROLES = ['Admin', 'Guru', 'Siswa', 'Karyawan'];

    // Admin hanya boleh membuat peran non-Admin
    private const ADMIN_ALLOWED_ROLES = ['Guru', 'Siswa', 'Karyawan'];

    function register(Request $request) {
        try {
            $requester = Auth::user();

            $allowedRoles = $requester->role === 'SuperAdmin'
                ? self::REGISTERABLE_ROLES
                : self::ADMIN_ALLOWED_ROLES;

            $validator = Validator::make($request->all(), [
                'name'             => 'required',
                'email'            => 'required|email|unique:users,email',
                'password'         => ['required', \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers()],
                'confirm_password' => 'required|same:password',
                'role'             => ['required', 'in:' . implode(',', $allowedRoles)],
            ], [
                'role.in' => "Role tidak valid. {$requester->role} hanya boleh membuat: " . implode(', ', $allowedRoles) . '.',
            ]);

            if ($validator->fails()) {
                return $this->response(
                    $validator->messages()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validator->errors()->all()
                );
            }

            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => $request->password,
                'role'     => $request->role,
            ]);

            $this->auditLog('registered', 'user', $user->email, [
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ]);

            return $this->response("User registered.", Response::HTTP_CREATED, [
                'user'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ]);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    function login(Request $request) {
        try {
            if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
                $user = Auth::user();

                // Cabut semua token lama agar tidak ada sesi ganda yang aktif
                $user->tokens()->where('revoked', false)->each(fn ($t) => $t->revoke());

                $token = $user->createToken("My Token")->accessToken;

                AuditLog::create([
                    'action'      => 'login',
                    'resource'    => 'user',
                    'resource_id' => $user->email,
                    'performed_by'=> $user->email,
                    'role'        => $user->role,
                    'ip_address'  => $request->ip(),
                    'payload'     => null,
                ]);

                return $this->response("Access granted.", Response::HTTP_OK, [
                    'token' => $token,
                    'user'  => $user->name,
                    'email' => $user->email,
                    'role'  => $user->role,
                ]);
            }

            return $this->response("Invalid user credentials.", Response::HTTP_BAD_REQUEST);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required',
                'new_password'     => ['required', 'different:current_password', \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers()],
                'confirm_password' => 'required|same:new_password',
            ]);

            if ($validator->fails()) {
                return $this->response(
                    $validator->messages()->first(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validator->errors()->all()
                );
            }

            $user = User::find(Auth::id());

            if (!Hash::check($request->current_password, $user->password)) {
                return $this->response('Password saat ini tidak sesuai.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $user->update(['password' => $request->new_password]);

            $this->auditLog('updated', 'user', $user->email, ['field' => 'password']);

            return $this->response('Password berhasil diubah.', Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return $this->response("Logged out.", Response::HTTP_OK);
    }
}
