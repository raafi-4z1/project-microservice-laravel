<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi_keluar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('siswa_id'); // lintas-service -> tanpa FK
            $table->date('tanggal');
            $table->dateTime('jam_keluar');
            $table->enum('jenis', ['pulang_awal', 'izin_kegiatan', 'lomba', 'pulang_sakit']);
            $table->string('keterangan')->nullable();
            // Disetujui wali kelas (user id) -> tanpa FK
            $table->unsignedBigInteger('disetujui_oleh');
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->timestamps();

            $table->index(['siswa_id', 'tanggal'], 'idx_keluar_siswa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_keluar');
    }
};
