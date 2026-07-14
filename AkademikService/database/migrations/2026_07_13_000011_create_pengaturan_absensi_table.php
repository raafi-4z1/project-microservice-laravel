<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_absensi', function (Blueprint $table) {
            $table->id();
            $table->string('tahun_ajaran', 9);
            $table->tinyInteger('semester')->unsigned();

            // Ambang jam. Masuk setelah batas_terlambat_* => status "terlambat".
            $table->time('jam_masuk_sekolah')->default('07:00');
            $table->time('batas_terlambat_siswa')->default('07:20');
            $table->time('jam_masuk_pegawai')->default('07:00');
            $table->time('batas_terlambat_pegawai')->default('07:20');

            $table->tinyInteger('durasi_pin_window_menit')->unsigned()->default(10);
            $table->unsignedInteger('radius_geofence_m')->default(150);
            $table->timestamps();

            $table->unique(['tahun_ajaran', 'semester'], 'unique_pengaturan_absensi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_absensi');
    }
};
