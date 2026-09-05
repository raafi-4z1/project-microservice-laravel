<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda "Petugas Acara": akun yang HANYA boleh mengelola agenda sekolah.
 *
 * Dibuat sebagai flag, bukan nilai `role` baru, mengikuti pola `is_admin_sekolah`
 * yang sudah terbukti: role tetap dipakai untuk hak dasar (mis. seorang guru yang
 * merangkap panitia acara tetap guru), penanda ini hanya MENAMBAH hak acara.
 *
 * Pembatasannya sendiri tidak bisa hanya mengandalkan `check.role` — banyak rute
 * baca sengaja tanpa gate (direktori, semester, jadwal). Karena itu ada
 * middleware terpisah yang menolak akun ber-flag ini di luar daftar-boleh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_petugas_acara')->default(false)->after('is_admin_sekolah');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_petugas_acara');
        });
    }
};
