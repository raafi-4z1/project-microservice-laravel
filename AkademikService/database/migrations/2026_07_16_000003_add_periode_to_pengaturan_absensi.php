<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan absensi kini bisa dibuat per periode:
 *  - periode_id null = default semester (mis. batas terlambat 07:20)
 *  - periode_id terisi = override selama periode itu (mis. Ramadan 08:00)
 *
 * unique(tahun_ajaran, semester) dilepas karena satu semester kini boleh punya
 * beberapa baris (default + per periode). Duplikat dicegah di application layer
 * (NULL dianggap berbeda oleh unique MySQL, jadi unique gabungan tidak menolong).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_absensi', function (Blueprint $table) {
            $table->dropUnique('unique_pengaturan_absensi');
        });

        Schema::table('pengaturan_absensi', function (Blueprint $table) {
            $table->unsignedBigInteger('periode_id')->nullable()->after('semester');

            $table->index(['tahun_ajaran', 'semester', 'periode_id'], 'idx_pengaturan_resolusi');
            $table->foreign('periode_id')->references('id')->on('periode_khusus')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_absensi', function (Blueprint $table) {
            $table->dropForeign(['periode_id']);
            $table->dropIndex('idx_pengaturan_resolusi');
            $table->dropColumn('periode_id');
        });

        Schema::table('pengaturan_absensi', function (Blueprint $table) {
            $table->unique(['tahun_ajaran', 'semester'], 'unique_pengaturan_absensi');
        });
    }
};
