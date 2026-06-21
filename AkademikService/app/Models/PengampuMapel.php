<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PengampuMapel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pengampu_mapels';

    protected $fillable = [
        'guru_id', 'mapel_id', 'kelas_id', 'tahun_ajaran', 'semester',
    ];
}
