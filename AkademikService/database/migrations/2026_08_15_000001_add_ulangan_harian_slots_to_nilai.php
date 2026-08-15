<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ulangan harian: dari SATU nilai menjadi maksimal LIMA per semester.
 *
 * `nilai_harian` TIDAK dihapus — perannya berubah menjadi kolom turunan berisi
 * rata-rata slot yang terisi. Dengan begitu semua pembaca lama (raport, rekap
 * angkatan, klien Android versi sekarang) tetap berfungsi tanpa perubahan.
 *
 * Slot kosong = NULL dan TIDAK ikut dihitung: 3 ulangan terisi -> dibagi 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nilai', function (Blueprint $table) {
            foreach (range(1, 5) as $i) {
                $table->decimal("nilai_harian_{$i}", 5, 2)->nullable()->after('pengampu_mapel_id');
            }
        });

        // Nilai harian lama = hasil satu kali ulangan -> pindahkan ke slot 1.
        // Rata-rata dari satu nilai sama dengan nilai itu sendiri, jadi kolom
        // turunan `nilai_harian` tetap konsisten tanpa perlu dihitung ulang.
        DB::table('nilai')->whereNotNull('nilai_harian')
            ->update(['nilai_harian_1' => DB::raw('nilai_harian')]);
    }

    public function down(): void
    {
        Schema::table('nilai', function (Blueprint $table) {
            $table->dropColumn([
                'nilai_harian_1', 'nilai_harian_2', 'nilai_harian_3',
                'nilai_harian_4', 'nilai_harian_5',
            ]);
        });
    }
};
