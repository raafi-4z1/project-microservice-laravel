<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acara = agenda sekolah untuk kalender bulanan (libur, ujian, kegiatan, rapat,
 * upacara, lainnya).
 *
 * SENGAJA terpisah dari `periode_khusus`. Keduanya sama-sama punya rentang
 * tanggal, tapi maknanya berbeda dan tidak boleh dicampur:
 *  - periode_khusus : mengubah ATURAN KBM (jam dipendekkan, KBM berhenti,
 *                     auto-alpa dilewati). Punya konsekuensi ke absensi & jadwal.
 *  - acara          : hanya AGENDA yang ditampilkan di kalender. Tidak mengubah
 *                     aturan apa pun.
 *
 * Menggabungkannya akan memaksa setiap agenda sepele (rapat, upacara) ikut
 * memengaruhi mesin absensi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acara', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 150);
            $table->enum('kategori', ['libur', 'ujian', 'kegiatan', 'rapat', 'upacara', 'lainnya']);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');          // sama dgn mulai = 1 hari
            $table->boolean('seharian')->default(true);
            $table->time('jam_mulai')->nullable();    // wajib bila seharian = false
            $table->time('jam_selesai')->nullable();
            $table->string('lokasi', 100)->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Kueri utamanya selalu "acara yang BERSINGGUNGAN dengan rentang bulan".
            $table->index(['tanggal_mulai', 'tanggal_selesai'], 'idx_acara_rentang');
            $table->index('kategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acara');
    }
};
