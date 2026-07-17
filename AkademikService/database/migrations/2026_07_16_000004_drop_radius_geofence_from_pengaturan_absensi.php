<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buang `radius_geofence_m` — kolom mati.
 * Radius geofence dipegang PER TERMINAL (`terminals.radius_m` di Gateway), yang
 * lebih tepat karena tiap terminal punya lokasi & radius sendiri. Kolom di sini
 * tidak pernah dibaca kode mana pun dan hanya menyesatkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_absensi', function (Blueprint $table) {
            $table->dropColumn('radius_geofence_m');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_absensi', function (Blueprint $table) {
            $table->unsignedInteger('radius_geofence_m')->default(150);
        });
    }
};
