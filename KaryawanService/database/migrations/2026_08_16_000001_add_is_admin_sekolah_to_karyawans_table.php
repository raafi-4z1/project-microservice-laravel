<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda "Administrator Sekolah" (staf Tata Usaha) pada data karyawan.
 *
 * Ini sisi DOMAIN: dipakai agar daftar/detail karyawan bisa menampilkan siapa
 * saja stafnya. Keputusan otorisasi TIDAK dibaca dari sini melainkan dari
 * `users.is_admin_sekolah` di Gateway (lihat migrasi kembarannya) — Gateway
 * menulis keduanya dalam satu operasi saat karyawan dibuat/diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->boolean('is_admin_sekolah')->default(false)->after('jabatan');
        });
    }

    public function down(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->dropColumn('is_admin_sekolah');
        });
    }
};
