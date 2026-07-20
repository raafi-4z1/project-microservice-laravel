<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengaturanAbsensi extends Model
{
    protected $table = 'pengaturan_absensi';

    protected $fillable = [
        'tahun_ajaran', 'semester', 'periode_id',
        'jam_masuk_sekolah', 'batas_terlambat_siswa',
        'jam_masuk_pegawai', 'batas_terlambat_pegawai',
        'durasi_pin_window_menit',
    ];

    protected $casts = [
        'semester'                => 'integer',
        'periode_id'              => 'integer',
        'durasi_pin_window_menit' => 'integer',
    ];

    public function periode()
    {
        return $this->belongsTo(PeriodeKhusus::class, 'periode_id');
    }

    /**
     * Pengaturan efektif: override periode kalau ada, else default semester.
     */
    public static function efektif(string $tahunAjaran, $semester, ?int $periodeId): ?self
    {
        if ($periodeId !== null) {
            $override = static::where('tahun_ajaran', $tahunAjaran)
                ->where('semester', $semester)
                ->where('periode_id', $periodeId)
                ->first();
            if ($override) return $override;
        }

        return static::where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->whereNull('periode_id')
            ->first();
    }
}
