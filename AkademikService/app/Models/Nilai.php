<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nilai extends Model
{
    use SoftDeletes;

    protected $table = 'nilai';

    protected $fillable = [
        'siswa_kelas_id', 'pengampu_mapel_id',
        'nilai_harian', 'nilai_uts', 'nilai_uas', 'nilai_akhir',
    ];

    protected $casts = [
        'nilai_harian' => 'float',
        'nilai_uts'    => 'float',
        'nilai_uas'    => 'float',
        'nilai_akhir'  => 'float',
    ];

    public function siswaKelas()
    {
        return $this->belongsTo(SiswaKelas::class);
    }

    public function pengampuMapel()
    {
        return $this->belongsTo(PengampuMapel::class);
    }
}
