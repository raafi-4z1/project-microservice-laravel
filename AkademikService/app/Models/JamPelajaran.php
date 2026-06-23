<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JamPelajaran extends Model
{
    protected $table = 'jam_pelajaran';

    protected $fillable = ['ke', 'jam_mulai', 'jam_selesai'];
}
