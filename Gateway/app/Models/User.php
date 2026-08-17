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
        'password',
        'must_change_password',
    ];

    /**
     * `isAdminSekolah` (camelCase) ikut di setiap respons yang mengembalikan
     * model ini — mis. GET /user — supaya klien bisa menentukan menu manajemen
     * tanpa panggilan tambahan. Kolom mentahnya disembunyikan agar tidak muncul
     * dua kali dengan gaya penamaan berbeda.
     */
    protected $appends = ['isAdminSekolah'];

    public function getIsAdminSekolahAttribute(): bool
    {
        return $this->role === 'Karyawan'
            && (bool) ($this->attributes['is_admin_sekolah'] ?? false);
    }

    /** Administrator Sekolah = karyawan yang diberi hak tulis operasional. */
    public function isAdminSekolah(): bool
    {
        return $this->getIsAdminSekolahAttribute();
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
