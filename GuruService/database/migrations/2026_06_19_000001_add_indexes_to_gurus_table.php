<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // gurus — tumbuh setiap ada penerimaan guru baru
        // Query yang akan sering dijalankan:
        //   - Filter status kepegawaian (PNS/honorer/dll): WHERE status_kepegawaian = ?
        //   - Soft delete scope: WHERE deleted_at IS NULL
        //   - Filter jabatan (kepala sekolah, wali kelas, dll): WHERE jabatan = ?
        Schema::table('gurus', function (Blueprint $table) {
            $table->index('status_kepegawaian', 'idx_gurus_status_kepeg');
            $table->index('jabatan', 'idx_gurus_jabatan');
            $table->index('deleted_at', 'idx_gurus_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('gurus', function (Blueprint $table) {
            $table->dropIndex('idx_gurus_status_kepeg');
            $table->dropIndex('idx_gurus_jabatan');
            $table->dropIndex('idx_gurus_deleted');
        });
    }
};
