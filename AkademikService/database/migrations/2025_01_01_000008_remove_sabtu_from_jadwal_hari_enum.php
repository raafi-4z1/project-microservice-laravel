<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Sekolah hanya beroperasi Senin-Jumat. Sabtu dihapus dari enum hari.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE jadwal_pelajaran MODIFY COLUMN hari ENUM('Senin','Selasa','Rabu','Kamis','Jumat') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE jadwal_pelajaran MODIFY COLUMN hari ENUM('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') NOT NULL");
    }
};
