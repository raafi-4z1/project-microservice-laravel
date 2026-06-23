<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Unique constraint (pengampu_mapel_id, hari, jam_mulai_id) tidak kompatibel dengan
// soft-delete: record soft-deleted tetap ada di DB dan memblokir INSERT/UPDATE baru
// dengan key yang sama. Ganti dengan regular index untuk performa query.
// Duplikat aktif dicegah di application layer (hasKelasConflict + hasGuruConflict).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_pelajaran', function (Blueprint $table) {
            $table->dropUnique('unique_jadwal_slot');
            $table->index(['pengampu_mapel_id', 'hari', 'jam_mulai_id'], 'idx_jp_slot');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_pelajaran', function (Blueprint $table) {
            $table->dropIndex('idx_jp_slot');
            $table->unique(['pengampu_mapel_id', 'hari', 'jam_mulai_id'], 'unique_jadwal_slot');
        });
    }
};
