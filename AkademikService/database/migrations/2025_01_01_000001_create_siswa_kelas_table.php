<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa_kelas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('siswa_id');
            $table->unsignedBigInteger('kelas_id');
            $table->string('tahun_ajaran', 9); // format: 2024/2025
            $table->enum('semester', ['1', '2']);
            $table->timestamps();
            $table->softDeletes();

            // Satu siswa hanya boleh di satu kelas per semester
            $table->unique(['siswa_id', 'tahun_ajaran', 'semester'], 'unique_siswa_per_semester');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa_kelas');
    }
};
