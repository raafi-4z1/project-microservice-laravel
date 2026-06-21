<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SemesterAktif extends Model
{
    protected $table = 'semester_aktif';

    protected $fillable = [
        'tahun_ajaran',
        'semester',
        'tanggal_mulai',
        'tanggal_selesai',
        'is_aktif',
    ];

    protected $casts = [
        'is_aktif'         => 'boolean',
        'tanggal_mulai'    => 'date',
        'tanggal_selesai'  => 'date',
    ];
}
