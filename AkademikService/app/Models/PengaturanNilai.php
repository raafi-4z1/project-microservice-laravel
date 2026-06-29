<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengaturanNilai extends Model
{
    protected $table = 'pengaturan_nilai';

    protected $fillable = ['tahun_ajaran', 'semester', 'bobot_harian', 'bobot_uts', 'bobot_uas'];

    protected $casts = [
        'semester'     => 'integer',
        'bobot_harian' => 'integer',
        'bobot_uts'    => 'integer',
        'bobot_uas'    => 'integer',
    ];
}
