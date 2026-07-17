<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiKeluar extends Model
{
    protected $table = 'absensi_keluar';

    protected $fillable = [
        'siswa_id', 'tanggal', 'jam_keluar', 'jenis',
        'keterangan', 'disetujui_oleh', 'terminal_id',
    ];

    protected $casts = [
        'tanggal'    => 'date',
        'jam_keluar' => 'datetime',
    ];
}
