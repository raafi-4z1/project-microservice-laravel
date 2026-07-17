<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\FileBase64Service;

class Guru extends Model
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'nik',
        'nip',
        'nama_lengkap',
        'telephone',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'status_pernikahan',
        'alamat',
        'foto',
        'status_kepegawaian',
        'nomor_sk_pengangkatan',
        'tanggal_masuk',
        'jabatan',
        'nomor_sertifikasi',
        'pendidikan_terakhir',
        'jurusan',
        'universitas',
        'tahun_lulus',
        'pelatihan',
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
        ];
    }

    /**
     * Override atribut 'foto' (path) dengan string Base64
     *
     * @param  string|null  $value  Path asli dari DB
     * @return string|null          Data image Base64
     */
    public function getFotoAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        return FileBase64Service::encode($value);
    }
}
