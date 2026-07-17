<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pin_windows', function (Blueprint $table) {
            $table->id();
            // Subjek yang boleh absen via PIN dalam jendela ini
            $table->enum('subjek_tipe', ['guru', 'karyawan']);
            $table->unsignedBigInteger('subjek_id');
            // User (admin) yang membuka jendela
            $table->unsignedBigInteger('dibuka_oleh');
            $table->timestamp('dibuka_at');
            $table->timestamp('berlaku_sampai');
            // Diisi saat PIN dipakai (sekali pakai)
            $table->timestamp('terpakai_at')->nullable();
            $table->timestamps();

            $table->index(['subjek_tipe', 'subjek_id']);
            $table->index('berlaku_sampai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pin_windows');
    }
};
