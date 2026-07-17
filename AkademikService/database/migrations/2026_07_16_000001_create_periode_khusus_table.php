<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periode khusus = rentang tanggal yang mengubah aturan akademik sementara:
 *  - ramadan : jam pelajaran dipendekkan (urutan mapel tetap)
 *  - ujian   : KBM normal berhenti (kbm_normal = false)
 *  - libur   : tidak ada sekolah (auto-alpa dilewati)
 *  - khusus  : kegiatan/jam khusus lain
 *
 * Aturan resolusi: untuk sebuah tanggal, periode yang menang adalah yang
 * rentangnya PALING PENDEK (paling spesifik). Jadi libur 1 hari di tengah
 * Ramadan otomatis mengalahkan Ramadan tanpa aturan tambahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_khusus', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);                    // mis. "Ramadan 1447"
            $table->string('tahun_ajaran', 9);
            $table->tinyInteger('semester')->unsigned();
            $table->enum('jenis', ['ramadan', 'ujian', 'libur', 'khusus']);
            $table->date('berlaku_dari');
            $table->date('berlaku_sampai');                 // sama dgn dari = 1 hari
            // false = KBM normal berhenti (ujian/libur) -> absensi pelajaran nonaktif
            $table->boolean('kbm_normal')->default(true);
            $table->string('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tahun_ajaran', 'semester'], 'idx_periode_semester');
            $table->index(['berlaku_dari', 'berlaku_sampai'], 'idx_periode_rentang');
            $table->index('jenis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_khusus');
    }
};
