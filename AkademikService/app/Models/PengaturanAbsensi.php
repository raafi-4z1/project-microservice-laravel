<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengaturanAbsensi extends Model
{
    protected $table = 'pengaturan_absensi';

    protected $fillable = [
        'tahun_ajaran', 'semester',
        'jam_masuk_sekolah', 'batas_terlambat_siswa',
        'jam_masuk_pegawai', 'batas_terlambat_pegawai',
        'durasi_pin_window_menit', 'radius_geofence_m',
    ];

    protected $casts = [
        'semester'                => 'integer',
        'durasi_pin_window_menit' => 'integer',
        'radius_geofence_m'       => 'integer',
    ];
}
