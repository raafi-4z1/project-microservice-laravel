<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semester_aktif', function (Blueprint $table) {
            $table->id();
            $table->string('tahun_ajaran', 9);  // format: 2024/2025
            $table->enum('semester', ['1', '2']);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->boolean('is_aktif')->default(false);
            $table->timestamps();

            $table->index('is_aktif', 'idx_sa_aktif');
            $table->index(['tahun_ajaran', 'semester'], 'idx_sa_periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semester_aktif');
    }
};
