<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karyawans', function (Blueprint $table) {
            $table->id();
            $table->string('email', 50)->unique();
            $table->string('nip', 20)->unique();
            $table->string('nama_lengkap', 75);
            $table->string('jabatan', 50);
            $table->string('status_kepegawaian', 35)->nullable();
            $table->enum('jenis_kelamin', ['Laki-Laki', 'Perempuan'])->nullable();
            $table->string('no_telp', 15)->nullable();
            $table->text('alamat')->nullable();
            $table->string('foto')->nullable();

            // Kartu absensi (opaque UID; barcode meng-encode kolom ini)
            $table->string('kartu_uid', 32)->nullable()->unique();
            $table->enum('kartu_status', ['belum_terbit', 'aktif', 'hilang', 'blokir'])
                  ->default('belum_terbit');
            $table->timestamp('kartu_diterbitkan_at')->nullable();

            // PIN absensi (fallback lupa kartu) — bcrypt, bukan password akun
            $table->string('pin_hash')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('kartu_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawans');
    }
};
