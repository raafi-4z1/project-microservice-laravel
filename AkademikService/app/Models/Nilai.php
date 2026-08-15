<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nilai extends Model
{
    use SoftDeletes;

    protected $table = 'nilai';

    /** Jumlah maksimal ulangan harian per semester. */
    public const MAX_ULANGAN = 5;

    protected $fillable = [
        'siswa_kelas_id', 'pengampu_mapel_id',
        'nilai_harian_1', 'nilai_harian_2', 'nilai_harian_3',
        'nilai_harian_4', 'nilai_harian_5',
        // Kolom turunan: rata-rata slot yang terisi (diisi controller, bukan input)
        'nilai_harian',
        'nilai_uts', 'nilai_uas', 'nilai_akhir',
    ];

    protected $casts = [
        'nilai_harian_1' => 'float',
        'nilai_harian_2' => 'float',
        'nilai_harian_3' => 'float',
        'nilai_harian_4' => 'float',
        'nilai_harian_5' => 'float',
        'nilai_harian'   => 'float',
        'nilai_uts'      => 'float',
        'nilai_uas'      => 'float',
        'nilai_akhir'    => 'float',
    ];

    /** Nilai tiap slot ulangan harian, urut 1..5 (null = belum ada ulangan). */
    public function ulanganHarian(): array
    {
        return array_map(
            fn($i) => $this->{"nilai_harian_{$i}"},
            range(1, self::MAX_ULANGAN)
        );
    }

    /**
     * Rata-rata ulangan harian: slot kosong DIABAIKAN, bukan dihitung 0.
     * 3 ulangan terisi -> dibagi 3. Belum ada ulangan sama sekali -> null.
     */
    public function rataUlanganHarian(): ?float
    {
        $terisi = array_values(array_filter($this->ulanganHarian(), fn($v) => $v !== null));
        return empty($terisi) ? null : round(array_sum($terisi) / count($terisi), 2);
    }

    public function siswaKelas()
    {
        return $this->belongsTo(SiswaKelas::class);
    }

    public function pengampuMapel()
    {
        return $this->belongsTo(PengampuMapel::class);
    }
}
