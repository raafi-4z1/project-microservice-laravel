<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\FileBase64Service;

class Karyawan extends Model
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'nip',
        'nama_lengkap',
        'jabatan',
        // Penanda staf Tata Usaha ("Administrator Sekolah"). Otorisasi dibaca
        // Gateway dari users.is_admin_sekolah; kolom ini sisi domain agar
        // daftar/detail karyawan bisa menampilkannya.
        'is_admin_sekolah',
        'status_kepegawaian',
        'jenis_kelamin',
        'no_telp',
        'alamat',
        'foto',
        // Kartu absensi & PIN (dikelola lewat alur penerbitan kartu, bukan CRUD biasa)
        'kartu_uid',
        'kartu_status',
        'kartu_diterbitkan_at',
        'pin_hash',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'kartu_diterbitkan_at' => 'datetime',
            // boolean asli di JSON, bukan 1/0 — klien memakainya untuk gating menu
            'is_admin_sekolah'     => 'boolean',
        ];
    }

    /**
     * Override atribut 'foto' (path) dengan string Base64.
     */
    public function getFotoAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        return FileBase64Service::encode($value);
    }
}
