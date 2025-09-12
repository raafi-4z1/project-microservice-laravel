<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gurus', function (Blueprint $table) {
            $table->id();
            $table->string('email', 50)->unique('gurus_email_unique');
            $table->string('nik', 16)->unique();
            $table->string('nip', 16)->unique();
            $table->string('nama_lengkap', 75);
            $table->string('telephone', 15);
            $table->enum('jenis_kelamin', ['Laki-Laki', 'Perempuan']);
            $table->string('tempat_lahir', 75);
            $table->date('tanggal_lahir');
            $table->enum('agama', ['Islam', 'Budha', 'Kristen Protestan', 'Katolik', 'Hindu'])->nullable();
            $table->enum('status_pernikahan', ['Menikah', 'Lajang', 'Single'])->nullable();
            $table->text('alamat');
            $table->string('foto');
            $table->string('status_kepegawaian', 35);
            $table->string('nomor_sk_pengangkatan', 50)->nullable();
            $table->date('tanggal_masuk');
            $table->string('jabatan', 25);
            $table->string('nomor_sertifikasi', 35)->nullable();
            $table->string('pendidikan_terakhir', 50);
            $table->string('jurusan', 75);
            $table->string('universitas', 100);
            $table->string('tahun_lulus', 7);
            $table->string('pelatihan', 75)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gurus');
    }
};
