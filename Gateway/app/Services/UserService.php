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
    public function create(string $name, string $email, string $role, bool $isAdminSekolah = false) {
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
        // Penanda Administrator Sekolah (hanya bermakna untuk role Karyawan)
        if (Schema::hasColumn('users', 'is_admin_sekolah')) {
            $attributes['is_admin_sekolah'] = $isAdminSekolah && $role === 'Karyawan';
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

    /**
     * Nyalakan/matikan penanda Administrator Sekolah pada akun.
     * Saat dicabut, seluruh token aktif ikut dicabut supaya hak tulis yang
     * sudah tidak berlaku tidak terus dipakai sampai token kedaluwarsa.
     */
    public function setAdminSekolah($email, bool $aktif) {
        if (!Schema::hasColumn('users', 'is_admin_sekolah')) {
            return;
        }
        $user = User::where('email', $email)->first();
        if (!$user || $user->role !== 'Karyawan') {
            return;
        }
        $sebelum = (bool) $user->getAttribute('is_admin_sekolah');
        $user->update(['is_admin_sekolah' => $aktif]);

        if ($sebelum && !$aktif) {
            $user->tokens()->where('revoked', false)->each(fn($t) => $t->revoke());
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
