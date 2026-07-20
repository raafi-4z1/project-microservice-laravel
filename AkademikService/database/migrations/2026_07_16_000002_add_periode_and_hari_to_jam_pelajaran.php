<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jam pelajaran kini bervariasi per periode & per hari:
 *  - periode_id null = set jam NORMAL; terisi = set jam periode itu (mis. Ramadan)
 *  - hari      null = berlaku semua hari; terisi = khusus hari itu (mis. Jumat)
 *
 * unique('ke') dilepas karena satu 'ke' kini boleh punya banyak baris
 * (normal + Ramadan, umum + khusus Jumat). Unique gabungan
 * (periode_id, hari, ke) TIDAK dipakai: MySQL menganggap NULL saling berbeda,
 * sehingga baris (null, null, 1) masih bisa duplikat. Duplikat dicegah di
 * application layer (WaliKelas/Jadwal memakai pola yang sama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->dropUnique(['ke']);
        });

        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->unsignedBigInteger('periode_id')->nullable()->after('id');
            $table->enum('hari', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'])
                  ->nullable()->after('periode_id');

            $table->index(['periode_id', 'hari', 'ke'], 'idx_jam_resolusi');
            $table->foreign('periode_id')->references('id')->on('periode_khusus')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->dropForeign(['periode_id']);
            $table->dropIndex('idx_jam_resolusi');
            $table->dropColumn(['periode_id', 'hari']);
        });

        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->unique('ke');
        });
    }
};
