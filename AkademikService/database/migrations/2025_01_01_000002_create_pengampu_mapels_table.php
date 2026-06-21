<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengampu_mapels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guru_id');
            $table->unsignedBigInteger('mapel_id');
            $table->unsignedBigInteger('kelas_id');
            $table->string('tahun_ajaran', 9); // format: 2024/2025
            $table->enum('semester', ['1', '2']);
            $table->timestamps();
            $table->softDeletes();

            // Satu mapel di satu kelas hanya diampu satu guru per semester
            $table->unique(['mapel_id', 'kelas_id', 'tahun_ajaran', 'semester'], 'unique_mapel_per_kelas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengampu_mapels');
    }
};
