<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminPassword = env('ADMIN_DEFAULT_PASSWORD');

        if (empty($adminPassword)) {
            throw new RuntimeException(
                "Seguridad (CWE-521): La variable de entorno ADMIN_DEFAULT_PASSWORD es obligatoria para inicializar " .
                "el usuario administrador SRE. Configure ADMIN_DEFAULT_PASSWORD en su entorno o archivo .env."
            );
        }

        $adminEmail = env('ADMIN_EMAIL', 'admin@devops60k.local');
        $adminName = env('ADMIN_NAME', 'SRE Admin');

        User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
            ]
        );
    }
}
