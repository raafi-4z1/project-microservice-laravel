<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JamPelajaran extends Model
{
    protected $table = 'jam_pelajaran';

    protected $fillable = ['periode_id', 'hari', 'ke', 'jam_mulai', 'jam_selesai'];

    protected $casts = [
        'periode_id' => 'integer',
        'ke'         => 'integer',
    ];

    public function periode()
    {
        return $this->belongsTo(PeriodeKhusus::class, 'periode_id');
    }

    /**
     * Jam efektif untuk slot (ke) pada periode + hari tertentu.
     *
     * Aturan:
     *  - Kalau periode punya set jam sendiri, set itu MENGGANTIKAN set normal —
     *    slot yang tidak didefinisikan berarti memang ditiadakan pada periode
     *    tsb (mis. Ramadan hanya sampai ke-6) => null.
     *  - Kalau periode tidak punya set jam (mis. hanya mengubah ambang absensi),
     *    pakai set normal.
     *  - Dalam satu set, baris ber-`hari` spesifik menang atas baris `hari` null
     *    (mis. Jumat lebih pendek daripada hari lain).
     */
    public static function efektif(?int $periodeId, string $hari, int $ke): ?self
    {
        $pakaiSetPeriode = $periodeId !== null
            && static::where('periode_id', $periodeId)->exists();

        return static::cari($pakaiSetPeriode ? $periodeId : null, $hari, $ke);
    }

    /** Seluruh slot pada set yang berlaku untuk periode + hari (urut ke). */
    public static function setEfektif(?int $periodeId, ?string $hari = null)
    {
        $pakaiSetPeriode = $periodeId !== null
            && static::where('periode_id', $periodeId)->exists();
        $setId = $pakaiSetPeriode ? $periodeId : null;

        $q = static::query()
            ->where(fn($x) => $setId === null ? $x->whereNull('periode_id') : $x->where('periode_id', $setId));

        if ($hari !== null) {
            $q->where(fn($x) => $x->where('hari', $hari)->orWhereNull('hari'));
        }

        // Baris hari-spesifik menang: ambil satu per `ke`
        return $q->orderBy('ke')->orderByRaw('hari IS NULL ASC')->get()->unique('ke')->values();
    }

    private static function cari(?int $periodeId, string $hari, int $ke): ?self
    {
        return static::where('ke', $ke)
            ->where(fn($q) => $periodeId === null ? $q->whereNull('periode_id') : $q->where('periode_id', $periodeId))
            ->where(fn($q) => $q->where('hari', $hari)->orWhereNull('hari'))
            ->orderByRaw('hari IS NULL ASC') // hari spesifik dulu, baru fallback semua-hari
            ->first();
    }
}
