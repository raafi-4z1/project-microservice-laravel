<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ApiResponser;
use App\Traits\LogsAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserManagementController extends Controller
{
    use ApiResponser, LogsAudit;

    // SuperAdmin tidak bisa dihapus via API oleh siapapun
    private const UNDELETABLE_ROLES = ['SuperAdmin'];

    // Role yang Admin boleh hapus
    private const ADMIN_DELETABLE_ROLES = ['Guru', 'Siswa', 'Karyawan'];

    public function index(Request $request)
    {
        $query = User::select('id', 'name', 'email', 'role', 'must_change_password', 'created_at');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Cari di nama atau email
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            });
        }

        $users = $query->paginate($request->get('per_page', 10));

        return $this->response('Data users.', Response::HTTP_OK, $users);
    }

    public function show($id)
    {
        $user = User::select('id', 'name', 'email', 'role', 'must_change_password', 'created_at')->find($id);

        if (!$user) {
            return $this->response('User tidak ditemukan.', Response::HTTP_NOT_FOUND);
        }

        return $this->response('Data user.', Response::HTTP_OK, $user);
    }

    public function destroy($id)
    {
        $requester = auth()->user();
        $target = User::find($id);

        if (!$target) {
            return $this->response('User tidak ditemukan.', Response::HTTP_NOT_FOUND);
        }

        // Tidak bisa menghapus akun sendiri
        if ($target->id === $requester->id) {
            return $this->response(
                'Tidak dapat menghapus akun sendiri.',
                Response::HTTP_FORBIDDEN
            );
        }

        // SuperAdmin tidak bisa dihapus via API oleh siapapun
        if (in_array($target->role, self::UNDELETABLE_ROLES)) {
            return $this->response(
                'Akun SuperAdmin tidak dapat dihapus melalui API.',
                Response::HTTP_FORBIDDEN
            );
        }

        // Admin hanya boleh menghapus Guru, Siswa, Karyawan
        if ($requester->role === 'Admin' && !in_array($target->role, self::ADMIN_DELETABLE_ROLES)) {
            return $this->response(
                'Admin hanya dapat menghapus akun dengan role: ' . implode(', ', self::ADMIN_DELETABLE_ROLES) . '.',
                Response::HTTP_FORBIDDEN
            );
        }

        // Cabut semua token aktif milik user yang dihapus
        $target->tokens()->where('revoked', false)->each(fn ($t) => $t->revoke());

        $target->delete(); // soft delete — data tetap di DB, deleted_at terisi

        $this->auditLog('deleted', 'user', $target->email, [
            'name'  => $target->name,
            'email' => $target->email,
            'role'  => $target->role,
        ]);

        return $this->response('Akun user berhasil dihapus.', Response::HTTP_ACCEPTED, [
            'name'  => $target->name,
            'email' => $target->email,
            'role'  => $target->role,
        ]);
    }

    public function resetPassword(Request $request, $id)
    {
        $requester = User::find(auth()->id());
        $target    = User::find($id);

        if (!$target) {
            return $this->response('User tidak ditemukan.', Response::HTTP_NOT_FOUND);
        }

        // Tidak bisa reset password akun sendiri lewat endpoint ini — pakai /password
        if ($target->id === $requester->id) {
            return $this->response(
                'Gunakan endpoint POST /password untuk mengubah password sendiri.',
                Response::HTTP_FORBIDDEN
            );
        }

        // SuperAdmin tidak bisa di-reset passwordnya via API
        if ($target->role === 'SuperAdmin') {
            return $this->response(
                'Password SuperAdmin tidak dapat direset melalui API.',
                Response::HTTP_FORBIDDEN
            );
        }

        // Admin hanya boleh reset password Guru, Siswa, Karyawan
        if ($requester->role === 'Admin' && !in_array($target->role, self::ADMIN_DELETABLE_ROLES)) {
            return $this->response(
                'Admin hanya dapat mereset password: ' . implode(', ', self::ADMIN_DELETABLE_ROLES) . '.',
                Response::HTTP_FORBIDDEN
            );
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'new_password'     => ['required', Password::min(8)->letters()->numbers()],
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return $this->response(
                $validator->messages()->first(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $validator->errors()->all()
            );
        }

        $target->update(['password' => Hash::make($request->new_password)]);

        // Cabut semua token aktif agar target wajib login ulang
        $target->tokens()->where('revoked', false)->each(fn ($t) => $t->revoke());

        $this->auditLog('updated', 'user', $target->email, [
            'field'      => 'password',
            'target'     => $target->email,
            'targetRole' => $target->role,
        ]);

        return $this->response('Password user berhasil direset. User harus login ulang.', Response::HTTP_OK, [
            'name'  => $target->name,
            'email' => $target->email,
            'role'  => $target->role,
        ]);
    }
}
