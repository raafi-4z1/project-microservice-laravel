<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa_kelas', function (Blueprint $table) {
            // Filter kelas + periode: GET /kelas/{id}/siswa?tahun_ajaran=&semester=
            $table->index(['kelas_id', 'tahun_ajaran', 'semester'], 'idx_sk_kelas_periode');
            // Mempercepat query withTrashed() untuk endpoint riwayat
            $table->index('deleted_at', 'idx_sk_deleted');
        });

        Schema::table('pengampu_mapels', function (Blueprint $table) {
            // Filter guru + periode: GET /guru/{id}/mapel?tahun_ajaran=&semester=
            $table->index(['guru_id', 'tahun_ajaran', 'semester'], 'idx_pm_guru_periode');
            // Filter kelas + periode: join query dari sisi kelas
            $table->index(['kelas_id', 'tahun_ajaran', 'semester'], 'idx_pm_kelas_periode');
            // Mempercepat query withTrashed() untuk endpoint riwayat
            $table->index('deleted_at', 'idx_pm_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('siswa_kelas', function (Blueprint $table) {
            $table->dropIndex('idx_sk_kelas_periode');
            $table->dropIndex('idx_sk_deleted');
        });

        Schema::table('pengampu_mapels', function (Blueprint $table) {
            $table->dropIndex('idx_pm_guru_periode');
            $table->dropIndex('idx_pm_kelas_periode');
            $table->dropIndex('idx_pm_deleted');
        });
    }
};
