<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapels', function (Blueprint $table) {
            $table->renameColumn('nama', 'nama_pelajaran');
        });
    }

    public function down(): void
    {
        Schema::table('mapels', function (Blueprint $table) {
            $table->renameColumn('nama_pelajaran', 'nama');
        });
    }
};
