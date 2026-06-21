<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Auth;

class UserService
{
    /**
     * Buat user baru
     */
    public function create(string $name, string $email, string $role) {
        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'role'     => $role,
            'password' => bcrypt($email),
        ]);

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
