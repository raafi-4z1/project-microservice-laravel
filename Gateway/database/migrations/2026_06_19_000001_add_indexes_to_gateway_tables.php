<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // users — query: WHERE role = ? (UserManagement filter) dan WHERE deleted_at IS NULL (soft delete)
        Schema::table('users', function (Blueprint $table) {
            $table->index('role', 'idx_users_role');
            $table->index('deleted_at', 'idx_users_deleted');
        });

        // audit_logs — tabel tumbuh paling cepat, satu row per operasi CRUD
        // Query yang akan sering dijalankan:
        //   - Riwayat aksi pada satu resource: WHERE resource = ? AND resource_id = ?
        //   - Aksi yang dilakukan user tertentu: WHERE performed_by = ?
        //   - Rentang waktu: WHERE created_at BETWEEN ? AND ?
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['resource', 'resource_id'], 'idx_al_resource');
            $table->index('performed_by', 'idx_al_actor');
            $table->index('created_at', 'idx_al_time');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role');
            $table->dropIndex('idx_users_deleted');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_al_resource');
            $table->dropIndex('idx_al_actor');
            $table->dropIndex('idx_al_time');
        });
    }
};
