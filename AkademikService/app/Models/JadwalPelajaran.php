<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JadwalPelajaran extends Model
{
    use SoftDeletes;

    protected $table = 'jadwal_pelajaran';

    protected $fillable = [
        'pengampu_mapel_id', 'hari', 'jam_mulai_id', 'jam_selesai_id', 'ruangan', 'catatan',
    ];

    public function pengampuMapel()
    {
        return $this->belongsTo(PengampuMapel::class);
    }

    public function jamMulai()
    {
        return $this->belongsTo(JamPelajaran::class, 'jam_mulai_id');
    }

    public function jamSelesai()
    {
        return $this->belongsTo(JamPelajaran::class, 'jam_selesai_id');
    }
}
