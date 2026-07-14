<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiPelajaran extends Model
{
    protected $table = 'absensi_pelajaran';

    protected $fillable = [
        'jadwal_id', 'siswa_id', 'tanggal', 'status',
        'keterangan', 'dicatat_oleh', 'tahun_ajaran', 'semester',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function jadwal()
    {
        return $this->belongsTo(JadwalPelajaran::class, 'jadwal_id');
    }
}
