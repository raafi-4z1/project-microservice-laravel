<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi_pelajaran', function (Blueprint $table) {
            $table->id();
            // jadwal_pelajaran ada di service ini -> FK asli (memuat kelas+mapel+guru+jam+hari)
            $table->foreignId('jadwal_id')->constrained('jadwal_pelajaran');
            // siswa_id & dicatat_oleh (guru_id) lintas-service -> tanpa FK
            $table->unsignedBigInteger('siswa_id');
            $table->date('tanggal'); // occurrence spesifik (jadwal berulang mingguan)
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpa']);
            $table->string('keterangan')->nullable();
            $table->unsignedBigInteger('dicatat_oleh'); // guru pengampu
            $table->string('tahun_ajaran', 9);
            $table->tinyInteger('semester')->unsigned();
            $table->timestamps();

            $table->unique(['jadwal_id', 'siswa_id', 'tanggal'], 'unique_absensi_pelajaran');
            $table->index(['siswa_id', 'tanggal'], 'idx_pelajaran_siswa');
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_pelajaran');
    }
};
