<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi_pegawai', function (Blueprint $table) {
            $table->id();
            // Guru & karyawan disatukan lewat subjek_tipe (id lintas-service -> tanpa FK)
            $table->enum('subjek_tipe', ['guru', 'karyawan']);
            $table->unsignedBigInteger('subjek_id');
            $table->date('tanggal');
            $table->dateTime('jam_masuk')->nullable();
            $table->dateTime('jam_pulang')->nullable();
            $table->enum('status', ['hadir', 'terlambat', 'izin', 'sakit', 'dinas_luar', 'alpa']);
            $table->enum('metode', ['scan', 'pin', 'manual']);
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->unsignedBigInteger('pin_window_id')->nullable();
            $table->unsignedBigInteger('dicatat_oleh')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['subjek_tipe', 'subjek_id', 'tanggal'], 'unique_absensi_pegawai');
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_pegawai');
    }
};
