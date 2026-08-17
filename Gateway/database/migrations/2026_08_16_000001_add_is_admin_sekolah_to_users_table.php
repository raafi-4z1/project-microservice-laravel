<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda "Administrator Sekolah" (staf Tata Usaha) pada akun.
 *
 * Hanya bermakna untuk akun berrole `Karyawan`: role tetap Karyawan (supaya
 * absensi pegawai, rekap/pegawai/saya, dan hak baca karyawan tidak berubah),
 * sementara flag ini yang membuka hak TULIS operasional setara Admin.
 *
 * Disimpan di Gateway karena keputusan otorisasi terjadi di sini pada setiap
 * request — menanyakannya ke KaryawanService tiap kali akan menambah satu
 * panggilan lintas-service pada jalur panas. Kolom kembarannya di
 * `karyawans.is_admin_sekolah` bersifat data domain (untuk ditampilkan di
 * daftar karyawan); Gateway yang menulis keduanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin_sekolah')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin_sekolah');
        });
    }
};
