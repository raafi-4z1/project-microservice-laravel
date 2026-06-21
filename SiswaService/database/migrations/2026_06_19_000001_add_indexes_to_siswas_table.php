<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // siswas — tabel terbesar di sistem (satu row per siswa, terakumulasi tiap tahun)
        // Query yang akan sering dijalankan:
        //   - Filter siswa aktif/lulus/pindah: WHERE status = 'Aktif' AND deleted_at IS NULL
        //   - Filter angkatan masuk: WHERE tanggal_masuk BETWEEN ? AND ?
        //   - Soft delete: WHERE deleted_at IS NULL (ditambah otomatis oleh SoftDeletes scope)
        Schema::table('siswas', function (Blueprint $table) {
            $table->index('status', 'idx_siswas_status');
            $table->index('tanggal_masuk', 'idx_siswas_masuk');
            $table->index('deleted_at', 'idx_siswas_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('siswas', function (Blueprint $table) {
            $table->dropIndex('idx_siswas_status');
            $table->dropIndex('idx_siswas_masuk');
            $table->dropIndex('idx_siswas_deleted');
        });
    }
};
