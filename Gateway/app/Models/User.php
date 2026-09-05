<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'is_admin_sekolah',
        'is_petugas_acara',
        'password',
        'must_change_password',
    ];

    /**
     * `isAdminSekolah` (camelCase) ikut di setiap respons yang mengembalikan
     * model ini — mis. GET /user — supaya klien bisa menentukan menu manajemen
     * tanpa panggilan tambahan. Kolom mentahnya disembunyikan agar tidak muncul
     * dua kali dengan gaya penamaan berbeda.
     */
    protected $appends = ['isAdminSekolah', 'isPetugasAcara'];

    public function getIsAdminSekolahAttribute(): bool
    {
        // Jebakan yang sudah pernah terjadi: query dengan select() parsial tidak
        // memuat kolomnya, sehingga accessor ini mengembalikan false untuk
        // Administrator Sekolah yang sebenarnya — bukan "data hilang" melainkan
        // "data salah", dan tidak ada yang gagal sehingga lolos tanpa disadari.
        // Untuk otorisasi false memang arah yang aman, tapi untuk respons ke klien
        // menyesatkan. Jadi kalau modelnya berasal dari DB tapi kolomnya tidak
        // ikut, catat peringatan agar ketahuan lewat log, bukan diam-diam.
        if ($this->exists && !array_key_exists('is_admin_sekolah', $this->attributes)) {
            \Illuminate\Support\Facades\Log::warning(
                'User::isAdminSekolah dibaca tanpa kolom is_admin_sekolah — '
                . 'tambahkan kolom itu ke select() agar nilainya tidak salah.',
                ['user_id' => $this->attributes['id'] ?? null]
            );
            return false;
        }

        return $this->role === 'Karyawan'
            && (bool) ($this->attributes['is_admin_sekolah'] ?? false);
    }

    /** Administrator Sekolah = karyawan yang diberi hak tulis operasional. */
    public function isAdminSekolah(): bool
    {
        return $this->getIsAdminSekolahAttribute();
    }

    /**
     * Petugas Acara = akun yang diberi hak mengelola agenda sekolah.
     *
     * Jebakan yang sama dengan isAdminSekolah: select() parsial membuat accessor
     * ini mengembalikan false untuk petugas yang sebenarnya — bukan "data hilang"
     * melainkan "data salah". Untuk otorisasi arahnya aman, untuk respons ke
     * klien menyesatkan, jadi dicatat ke log agar ketahuan.
     */
    public function getIsPetugasAcaraAttribute(): bool
    {
        if ($this->exists && !array_key_exists('is_petugas_acara', $this->attributes)) {
            \Illuminate\Support\Facades\Log::warning(
                'User::isPetugasAcara dibaca tanpa kolom is_petugas_acara — '
                . 'tambahkan kolom itu ke select() agar nilainya tidak salah.',
                ['user_id' => $this->attributes['id'] ?? null]
            );
            return false;
        }

        return (bool) ($this->attributes['is_petugas_acara'] ?? false);
    }

    public function isPetugasAcara(): bool
    {
        return $this->getIsPetugasAcaraAttribute();
    }

    /**
     * Pengelola sekolah = SuperAdmin, Admin, dan Administrator Sekolah.
     *
     * Dipakai untuk keputusan BACA data pribadi. Perhatikan bahwa role
     * `Karyawan` biasa (satpam, kebersihan, dsb.) TIDAK termasuk: ia anggota
     * sekolah yang boleh melihat direktori, tapi tidak berkepentingan atas NIK,
     * alamat, telepon, atau tanggal lahir rekan kerjanya.
     */
    public function isPengelola(): bool
    {
        return in_array($this->role, ['SuperAdmin', 'Admin'], true)
            || $this->isAdminSekolah();
    }

    /**
     * Boleh melihat data pribadi SISWA (NISN, tempat/tanggal lahir, alamat,
     * kontak orang tua). Guru ikut karena butuh menghubungi orang tua; siswa
     * lain dan karyawan biasa tidak.
     */
    public function bolehLihatPiiSiswa(): bool
    {
        return $this->isPengelola() || $this->role === 'Guru';
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'is_admin_sekolah', // diekspos sebagai `isAdminSekolah` (lihat $appends)
        'is_petugas_acara', // diekspos sebagai `isPetugasAcara`
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_admin_sekolah' => 'boolean',
        ];
    }
}
