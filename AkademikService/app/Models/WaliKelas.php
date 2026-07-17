<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaliKelas extends Model
{
    use SoftDeletes;

    protected $table = 'wali_kelas';

    protected $fillable = [
        'guru_id', 'kelas_id', 'tahun_ajaran', 'semester',
    ];
}
