<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiHarian extends Model
{
    protected $table = 'absensi_harian';

    protected $fillable = [
        'siswa_id', 'tanggal', 'tahun_ajaran', 'semester',
        'jam_masuk', 'status', 'metode',
        'terminal_id', 'dicatat_oleh', 'keterangan',
    ];

    protected $casts = [
        'tanggal'   => 'date',
        'jam_masuk' => 'datetime',
    ];
}
