<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel cache untuk `CACHE_STORE=database`.
 *
 * Semua service disetel `CACHE_STORE=database`, tapi berkas migrasinya dulu hanya
 * ada di Gateway. Akibatnya KaryawanService dan AkademikService berjalan TANPA
 * tabel ini: `php artisan cache:clear` — langkah yang diwajibkan prosedur deploy
 * di README setelah tiap `git pull` — gagal dengan SQLSTATE[42S02] di dua service
 * itu. Empat service lain kebetulan punya tabelnya dari instalasi lama, jadi
 * masalahnya tak terlihat sampai ada yang benar-benar memakai cache.
 *
 * `hasTable` dipakai supaya migrasi ini aman dijalankan di service yang tabelnya
 * sudah terlanjur ada tanpa catatan migrasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
