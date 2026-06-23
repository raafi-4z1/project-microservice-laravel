<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pengampu_mapel_id');
            $table->enum('hari', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']);
            $table->unsignedBigInteger('jam_mulai_id');   // FK ke jam_pelajaran
            $table->unsignedBigInteger('jam_selesai_id'); // FK ke jam_pelajaran
            $table->string('ruangan', 50)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Satu pengampu tidak boleh punya dua jadwal di hari + jam_mulai yang sama
            $table->unique(['pengampu_mapel_id', 'hari', 'jam_mulai_id'], 'unique_jadwal_slot');

            $table->index('pengampu_mapel_id', 'idx_jp_pengampu');
            $table->index('deleted_at', 'idx_jp_deleted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_pelajaran');
    }
};
