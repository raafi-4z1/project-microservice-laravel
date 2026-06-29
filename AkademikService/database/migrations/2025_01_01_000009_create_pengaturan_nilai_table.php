<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_nilai', function (Blueprint $table) {
            $table->id();
            $table->string('tahun_ajaran', 9);
            $table->tinyInteger('semester')->unsigned();
            // Bobot dalam persen, harus berjumlah 100
            $table->tinyInteger('bobot_harian')->unsigned()->default(40);
            $table->tinyInteger('bobot_uts')->unsigned()->default(30);
            $table->tinyInteger('bobot_uas')->unsigned()->default(30);
            $table->timestamps();

            $table->unique(['tahun_ajaran', 'semester'], 'unique_pengaturan_nilai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_nilai');
    }
};
