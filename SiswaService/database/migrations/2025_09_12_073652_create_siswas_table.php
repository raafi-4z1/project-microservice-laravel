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
        Schema::create('siswas', function (Blueprint $table) {
            $table->id();
            $table->string('email', 50)->unique('siswas_email_unique');
            $table->string('nisn', 16)->unique();
            $table->string('nama_lengkap', 75);
            $table->string('telephone', 15);
            $table->enum('jenis_kelamin', ['Laki-Laki', 'Perempuan']);
            $table->enum('status', ['Aktif', 'Lulus', 'Berhenti', 'Pindah'])->default('Aktif');
            $table->date('status_date')->nullable();
            $table->string('tempat_lahir', 75);
            $table->date('tanggal_lahir');
            $table->enum('agama', ['Islam', 'Budha', 'Kristen Protestan', 'Katolik', 'Hindu'])->nullable();
            $table->date('tanggal_masuk');
            $table->text('alamat');
            $table->string('foto');
            $table->string('nama_ayah', 75)->nullable();
            $table->string('nama_ibu', 75);
            $table->string('pekerjaan_ayah', 75)->nullable();
            $table->string('pekerjaan_ibu', 75)->nullable();
            $table->string('no_telp_ayah', 15)->nullable();
            $table->string('no_telp_ibu', 15)->nullable();
            $table->string('nama_wali', 75)->nullable();
            $table->string('hubungan_wali', 75)->nullable();
            $table->string('no_telp_wali', 15)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siswas');
    }
};
