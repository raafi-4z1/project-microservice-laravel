<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('role');
        });

        // Backfill: akun lama yang masih memakai password default (= email)
        // wajib ganti password saat login berikutnya. SuperAdmin dikecualikan.
        DB::table('users')
            ->where('role', '!=', 'SuperAdmin')
            ->orderBy('id')
            ->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    if (Hash::check($user->email, $user->password)) {
                        DB::table('users')
                            ->where('id', $user->id)
                            ->update(['must_change_password' => true]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
