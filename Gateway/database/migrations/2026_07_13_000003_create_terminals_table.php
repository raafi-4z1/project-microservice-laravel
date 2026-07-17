<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 75);
            // Lokasi menentukan jenis absen yang diterima:
            // gerbang -> siswa; ruang_guru/tu -> guru/karyawan
            $table->enum('lokasi', ['gerbang', 'ruang_guru', 'tu']);
            // SHA-256 dari token terminal (bukan token mentah)
            $table->string('token_hash', 64);
            $table->enum('mode', ['produksi', 'demo'])->default('demo');
            // Produksi: daftar IP/CIDR LAN yang diizinkan (comma-separated)
            $table->string('ip_allowlist')->nullable();
            // Demo: geofence titik sekolah
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('radius_m')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->index('is_aktif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminals');
    }
};
