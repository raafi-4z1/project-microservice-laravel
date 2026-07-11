<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Auth;
use Illuminate\Support\Facades\Schema;

class UserService
{
    /**
     * Buat user baru.
     * Password awal = email (agar guru/siswa bisa login pertama kali),
     * tapi akun ditandai wajib ganti password sebelum bisa mengakses fitur lain.
     */
    public function create(string $name, string $email, string $role) {
        $attributes = [
            'name'     => $name,
            'email'    => $email,
            'role'     => $role,
            'password' => bcrypt($email),
        ];

        // Guard: kolom baru — lewati jika migration belum dijalankan
        if (Schema::hasColumn('users', 'must_change_password')) {
            $attributes['must_change_password'] = true;
        }

        $user = User::create($attributes);

        if (!$user) {
            throw new Exception("Gagal membuat user.");
        }
    }

    public function update($email, string $nama) {
        $user = User::where('email', $email)->first();
        if ($user) {
            $user->update(['name' => $nama]);
        }
    }

    public function delete($email) {
        $user = User::where('email', $email)->first();
        if ($user) {
            $user->tokens()->where('revoked', false)->each(fn($t) => $t->revoke());
            $user->delete();
        }
    }
}
