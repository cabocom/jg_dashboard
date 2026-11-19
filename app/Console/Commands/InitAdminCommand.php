<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class InitAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashboard:init-admin 
                            {--email= : Correo electrónico del usuario administrador}
                            {--password= : Contraseña del usuario administrador}
                            {--name= : Nombre descriptivo del usuario administrador}
                            {--force : Sobrescribir contraseña si el usuario ya existe}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aprovisiona el usuario administrador inicial para el plano de control jg_dashboard de forma idempotente';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email') 
            ?? config('agent.admin_email', 'admin@javiergalvez.test.local');

        $name = $this->option('name') 
            ?? config('agent.admin_name', 'Administrador');

        $password = $this->option('password') 
            ?? config('agent.admin_default_password');

        if (empty($password)) {
            $this->error('Error de seguridad (CWE-521): La contraseña del administrador no puede estar vacía.');
            $this->line('Proporcione la opción --password o configure la variable ADMIN_DEFAULT_PASSWORD en su entorno.');
            return Command::FAILURE;
        }

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            if (!$this->option('force')) {
                $this->info("El usuario administrador [{$email}] ya existe. Omitiendo creación para evitar duplicaciones.");
                return Command::SUCCESS;
            }

            $existingUser->update([
                'name' => $name,
                'password' => Hash::make($password),
                'role' => 'admin',
            ]);

            $this->info("Contraseña y datos del administrador [{$email}] actualizados exitosamente (--force).");
            return Command::SUCCESS;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);

        $this->info("Usuario administrador [{$email}] aprovisionado exitosamente en jg_dashboard.");
        return Command::SUCCESS;
    }
}
