<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action');           // created | updated | deleted | registered
            $table->string('resource');         // guru | siswa | mapel | kelas | user
            $table->string('resource_id')->nullable(); // ID record yang terpengaruh
            $table->string('performed_by')->nullable(); // email pelaku
            $table->string('role', 30)->nullable();    // role pelaku
            $table->string('ip_address', 45)->nullable();
            $table->json('payload')->nullable();        // data yang dikirim (tanpa file/password)
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
