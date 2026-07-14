<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi_harian', function (Blueprint $table) {
            $table->id();
            // siswa_id lintas-service (SiswaService) -> tanpa FK
            $table->unsignedBigInteger('siswa_id');
            $table->date('tanggal');
            $table->string('tahun_ajaran', 9);
            $table->tinyInteger('semester')->unsigned();

            $table->dateTime('jam_masuk')->nullable();
            $table->enum('status', ['hadir', 'terlambat', 'izin', 'sakit', 'alpa']);
            $table->enum('metode', ['scan', 'manual', 'turunan']);

            // Terminal (jika scan) / user pencatat (jika manual). Lintas-service -> tanpa FK.
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->unsignedBigInteger('dicatat_oleh')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['siswa_id', 'tanggal'], 'unique_absensi_harian');
            $table->index('tanggal');
            $table->index(['tahun_ajaran', 'semester'], 'idx_harian_semester');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_harian');
    }
};
