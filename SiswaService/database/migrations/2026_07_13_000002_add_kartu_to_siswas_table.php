<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswas', function (Blueprint $table) {
            // Kartu absensi (opaque UID prefix SIS-; barcode meng-encode kolom ini)
            $table->string('kartu_uid', 32)->nullable()->unique()->after('foto');
            $table->enum('kartu_status', ['belum_terbit', 'aktif', 'hilang', 'blokir'])
                  ->default('belum_terbit')->after('kartu_uid');
            $table->timestamp('kartu_diterbitkan_at')->nullable()->after('kartu_status');

            $table->index('kartu_status');
        });
    }

    public function down(): void
    {
        Schema::table('siswas', function (Blueprint $table) {
            $table->dropIndex(['kartu_status']);
            $table->dropColumn(['kartu_uid', 'kartu_status', 'kartu_diterbitkan_at']);
        });
    }
};
