<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email    = env('SUPERADMIN_EMAIL');
        $password = env('SUPERADMIN_PASSWORD');
        $name     = env('SUPERADMIN_NAME');

        $banned = ['superadmin@example.com', 'admin@example.com', 'email_kamu@domain.com', 'GantiPasswordIni123', 'ChangeMe123', 'MinimalDuabelasKarakter1', 'password'];

        if (!$email || !$password || !$name) {
            $this->command->error('Set SUPERADMIN_NAME, SUPERADMIN_EMAIL, and SUPERADMIN_PASSWORD in .env before seeding.');
            exit(1);
        }

        if (in_array($email, $banned) || in_array($password, $banned)) {
            $this->command->error('Default credentials detected. Change SUPERADMIN_EMAIL and SUPERADMIN_PASSWORD in .env to unique values before seeding.');
            exit(1);
        }

        if (strlen($password) < 12) {
            $this->command->error('SUPERADMIN_PASSWORD must be at least 12 characters.');
            exit(1);
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => $name,
                'password' => $password,
                'role'     => 'SuperAdmin',
            ]
        );

        $this->command->info("SuperAdmin created: {$email}");
    }
}
