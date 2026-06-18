<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\FileBase64Service;

class Siswa extends Model
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'nisn',
        'nama_lengkap',
        'telephone',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'tanggal_masuk',
        'alamat',
        'status',
        'status_date',
        'foto',
        'nama_ayah',
        'nama_ibu',
        'pekerjaan_ayah',
        'pekerjaan_ibu',
        'no_telp_ayah',
        'no_telp_ibu',
        'nama_wali',
        'hubungan_wali',
        'no_telp_wali'
    ];
    

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
