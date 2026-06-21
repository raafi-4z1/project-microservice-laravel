<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiswaKelas extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siswa_kelas';

    protected $fillable = [
        'siswa_id', 'kelas_id', 'tahun_ajaran', 'semester',
    ];
}
