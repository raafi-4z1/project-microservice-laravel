<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Acara extends Model
{
    use SoftDeletes;

    protected $table = 'acara';

    public const KATEGORI = ['libur', 'ujian', 'kegiatan', 'rapat', 'upacara', 'lainnya'];

    protected $fillable = [
        'judul', 'kategori', 'tanggal_mulai', 'tanggal_selesai',
        'seharian', 'jam_mulai', 'jam_selesai', 'lokasi', 'keterangan',
    ];

    protected $casts = [
        'tanggal_mulai'  => 'date',
        'tanggal_selesai'=> 'date',
        'seharian'       => 'boolean',
    ];

    /**
     * Acara yang BERSINGGUNGAN dengan rentang [dari, sampai] — bukan yang
     * termuat seluruhnya di dalamnya.
     *
     * Kalender bulanan harus tetap menampilkan acara yang mulai bulan lalu dan
     * berakhir bulan ini. Syarat singgung dua rentang: mulai <= sampai DAN
     * selesai >= dari.
     */
    public function scopeBersinggungan(Builder $q, string $dari, string $sampai): Builder
    {
        return $q->whereDate('tanggal_mulai', '<=', $sampai)
                 ->whereDate('tanggal_selesai', '>=', $dari);
    }
}
