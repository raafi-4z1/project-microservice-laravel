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
    /**
     * Apakah email sudah dipakai akun yang MASIH AKTIF?
     *
     * Hanya akun aktif yang menghalangi. Akun yang sudah dihapus memang tetap
     * menahan emailnya di level DB (`users.email` unik + soft delete), tapi
     * penanganannya bukan menolak melainkan MEMULIHKAN — lihat create().
     *
     * Pemeriksaan ini tetap perlu dilakukan SEBELUM record domain ditulis:
     * `POST /guru|siswa|karyawan` menulis record domain lebih dulu, dan tidak ada
     * transaksi lintas-service. Kalau pembuatan akun baru gagal setelah itu,
     * record domainnya terlanjur tersimpan tanpa akun login.
     */
    public function emailDipakaiAktif(string $email): bool
    {
        return User::where('email', $email)->exists();
    }

    /** Akun terhapus yang masih memegang email ini (bila ada). */
    public function akunTerhapus(string $email): ?User
    {
        return User::onlyTrashed()->where('email', $email)->first();
    }

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

        // Email yang dipegang akun TERHAPUS dipulihkan, bukan ditolak.
        //
        // `users.email` unik di level DB dan User memakai soft delete, jadi akun
        // yang "dihapus" menahan emailnya selamanya. Sebelumnya itu berarti sebuah
        // email tak pernah bisa dipakai ulang — masalah nyata di sekolah (siswa
        // pindah lalu kembali, akun salah ketik terlanjur dihapus).
        //
        // Yang dipulihkan adalah BARIS yang sama, sehingga tautan ke record domain
        // (yang juga berkunci email) tetap utuh. Password dan penanda ditulis ulang
        // dari data baru — akun lama tidak boleh "hidup kembali" dengan kredensial
        // dan haknya yang dulu.
        $terhapus = $this->akunTerhapus($email);

        if ($terhapus) {
            $terhapus->restore();
            $terhapus->update($attributes);

            // Token lama milik akun itu dicabut. Tanpa ini, siapa pun yang masih
            // memegang token sebelum penghapusan langsung mendapat akses ke akun
            // yang kini milik orang lain.
            $terhapus->tokens()->where('revoked', false)->each(fn($t) => $t->revoke());

            $this->catatPemulihan($terhapus, $role);
            return;
        }

        $user = User::create($attributes);

        if (!$user) {
            throw new Exception("Gagal membuat user.");
        }
    }

    /**
     * Catat pemulihan akun ke audit log.
     *
     * Memulihkan akun terhapus adalah aksi istimewa: identitas lama hidup kembali
     * dan tertaut ke record domain yang sama. Kalau suatu saat dipertanyakan
     * ("kenapa akun ini ada lagi?"), inilah jejak yang menjawabnya.
     */
    private function catatPemulihan(User $user, string $role): void
    {
        try {
            $pelaku = Auth::user();

            \App\Models\AuditLog::create([
                'action'      => 'restored',
                'resource'    => 'user',
                'resource_id' => (string) $user->id,
                'performed_by'=> $pelaku?->email,
                'role'        => $pelaku?->role,
                'ip_address'  => request()->ip(),
                'payload'     => [
                    'email'         => $user->email,
                    'roleBaru'      => $role,
                    'tokenDicabut'  => 'true',
                ],
            ]);
        } catch (\Throwable $e) {
            // Gagal mencatat TIDAK boleh menggagalkan pemulihannya; tapi juga
            // tidak boleh hilang tanpa jejak.
            \Illuminate\Support\Facades\Log::warning(
                'Gagal menulis audit pemulihan akun: ' . $e->getMessage(),
                ['email' => $user->email]
            );
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
