<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PeriodeKhusus extends Model
{
    use SoftDeletes;

    protected $table = 'periode_khusus';

    protected $fillable = [
        'nama', 'tahun_ajaran', 'semester', 'jenis',
        'berlaku_dari', 'berlaku_sampai', 'kbm_normal', 'keterangan',
    ];

    protected $casts = [
        'semester'       => 'integer',
        'berlaku_dari'   => 'date',
        'berlaku_sampai' => 'date',
        'kbm_normal'     => 'boolean',
    ];

    /**
     * Periode yang berlaku pada sebuah tanggal.
     * Kalau beberapa periode bertumpuk (mis. libur 1 hari di tengah Ramadan),
     * yang menang adalah rentang TERPENDEK — paling spesifik.
     */
    public static function untukTanggal(string $tanggal): ?self
    {
        return static::whereDate('berlaku_dari', '<=', $tanggal)
            ->whereDate('berlaku_sampai', '>=', $tanggal)
            ->orderByRaw('DATEDIFF(berlaku_sampai, berlaku_dari) ASC')
            ->orderByDesc('id')
            ->first();
    }

    public function isLibur(): bool
    {
        return $this->jenis === 'libur';
    }
}
