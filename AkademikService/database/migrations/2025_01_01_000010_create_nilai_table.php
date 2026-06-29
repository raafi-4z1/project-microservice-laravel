<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nilai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_kelas_id')->constrained('siswa_kelas');
            $table->foreignId('pengampu_mapel_id')->constrained('pengampu_mapels');
            $table->decimal('nilai_harian', 5, 2)->nullable();
            $table->decimal('nilai_uts',    5, 2)->nullable();
            $table->decimal('nilai_uas',    5, 2)->nullable();
            // Dikalkulasi otomatis saat semua komponen terisi
            $table->decimal('nilai_akhir',  5, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Regular index bukan unique agar kompatibel dengan soft-delete;
            // duplikat aktif dicegah di application layer
            $table->index(['siswa_kelas_id', 'pengampu_mapel_id'], 'idx_nilai_slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai');
    }
};
