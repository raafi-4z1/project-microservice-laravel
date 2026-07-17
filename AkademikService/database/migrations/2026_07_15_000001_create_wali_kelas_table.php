<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wali_kelas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guru_id');   // lintas-service (GuruService) -> tanpa FK
            $table->unsignedBigInteger('kelas_id');  // lintas-service (ClassMicroservices) -> tanpa FK
            $table->string('tahun_ajaran', 9);       // format: 2024/2025
            $table->enum('semester', ['1', '2']);
            $table->timestamps();
            $table->softDeletes();

            // Satu kelas hanya punya satu wali per semester
            $table->unique(['kelas_id', 'tahun_ajaran', 'semester'], 'unique_wali_per_kelas');
            $table->index(['guru_id', 'tahun_ajaran', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wali_kelas');
    }
};
