<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiPegawai extends Model
{
    protected $table = 'absensi_pegawai';

    protected $fillable = [
        'subjek_tipe', 'subjek_id', 'tanggal',
        'jam_masuk', 'jam_pulang', 'status', 'metode',
        'terminal_id', 'pin_window_id', 'dicatat_oleh', 'keterangan',
    ];

    protected $casts = [
        'tanggal'    => 'date',
        'jam_masuk'  => 'datetime',
        'jam_pulang' => 'datetime',
    ];
}
