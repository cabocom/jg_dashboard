<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace Tests\Unit;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('ADMIN_DEFAULT_PASSWORD');
        unset($_ENV['ADMIN_DEFAULT_PASSWORD']);
        unset($_SERVER['ADMIN_DEFAULT_PASSWORD']);
        parent::tearDown();
    }

    public function test_seeder_throws_exception_when_admin_password_is_not_set(): void
    {
        putenv('ADMIN_DEFAULT_PASSWORD');
        unset($_ENV['ADMIN_DEFAULT_PASSWORD']);
        unset($_SERVER['ADMIN_DEFAULT_PASSWORD']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_DEFAULT_PASSWORD es obligatoria');

        $seeder = new DatabaseSeeder();
        $seeder->run();
    }

    public function test_seeder_creates_admin_user_with_explicit_password(): void
    {
        putenv('ADMIN_DEFAULT_PASSWORD=MiContrasenaSegura2026!');
        $_ENV['ADMIN_DEFAULT_PASSWORD'] = 'MiContrasenaSegura2026!';

        $seeder = new DatabaseSeeder();
        $seeder->run();

        $user = User::where('email', 'admin@javiergalvez.test.local')->first();
        $this->assertNotNull($user);
        $this->assertEquals('SRE Admin', $user->name);
        $this->assertEquals('admin', $user->role);
        $this->assertTrue(Hash::check('MiContrasenaSegura2026!', $user->password));
    }

    public function test_seeder_updates_existing_admin_user_on_password_rotation(): void
    {
        // Primer seed
        putenv('ADMIN_DEFAULT_PASSWORD=PrimeraPassword2026!');
        $_ENV['ADMIN_DEFAULT_PASSWORD'] = 'PrimeraPassword2026!';
        $seeder = new DatabaseSeeder();
        $seeder->run();

        $user1 = User::where('email', 'admin@javiergalvez.test.local')->first();
        $this->assertTrue(Hash::check('PrimeraPassword2026!', $user1->password));

        // Rotación de contraseña
        putenv('ADMIN_DEFAULT_PASSWORD=SegundaPasswordRotada2026#');
        $_ENV['ADMIN_DEFAULT_PASSWORD'] = 'SegundaPasswordRotada2026#';
        $seeder->run();

        $user2 = User::where('email', 'admin@javiergalvez.test.local')->first();
        $this->assertEquals($user1->id, $user2->id);
        $this->assertTrue(Hash::check('SegundaPasswordRotada2026#', $user2->password));
        $this->assertFalse(Hash::check('PrimeraPassword2026!', $user2->password));
    }
}
