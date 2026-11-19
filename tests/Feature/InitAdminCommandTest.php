<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config([
        'agent.admin_default_password' => null,
        'agent.admin_email' => 'admin@javiergalvez.test.local',
        'agent.admin_name' => 'Administrador',
    ]);
});

afterEach(function () {
    config([
        'agent.admin_default_password' => null,
        'agent.admin_email' => 'admin@javiergalvez.test.local',
        'agent.admin_name' => 'Administrador',
    ]);
});

test('dashboard:init-admin creates new admin user with cli options', function () {
    $this->artisan('dashboard:init-admin', [
        '--email' => 'new_admin@example.com',
        '--password' => 'SuperSecret1234!',
        '--name' => 'Custom Admin Name',
    ])->assertExitCode(0)
      ->expectsOutputToContain('aprovisionado exitosamente');

    $user = User::where('email', 'new_admin@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Custom Admin Name')
        ->and($user->role)->toBe('admin')
        ->and(Hash::check('SuperSecret1234!', $user->password))->toBeTrue();
});

test('dashboard:init-admin is idempotent and skips creation if user exists', function () {
    User::create([
        'name' => 'Preexisting Admin',
        'email' => 'idempotent_admin@example.com',
        'password' => Hash::make('original_pass'),
        'role' => 'admin',
    ]);

    $this->artisan('dashboard:init-admin', [
        '--email' => 'idempotent_admin@example.com',
        '--password' => 'different_pass',
    ])->assertExitCode(0)
      ->expectsOutputToContain('ya existe. Omitiendo creación para evitar duplicaciones');

    expect(User::where('email', 'idempotent_admin@example.com')->count())->toBe(1);

    $user = User::where('email', 'idempotent_admin@example.com')->first();
    expect(Hash::check('original_pass', $user->password))->toBeTrue();
});

test('dashboard:init-admin with force option updates existing admin password', function () {
    User::create([
        'name' => 'Original Admin',
        'email' => 'forced_admin@example.com',
        'password' => Hash::make('old_pass'),
        'role' => 'admin',
    ]);

    $this->artisan('dashboard:init-admin', [
        '--email' => 'forced_admin@example.com',
        '--password' => 'new_rotated_pass!',
        '--name' => 'Updated Admin',
        '--force' => true,
    ])->assertExitCode(0)
      ->expectsOutputToContain('actualizados exitosamente (--force)');

    expect(User::where('email', 'forced_admin@example.com')->count())->toBe(1);

    $user = User::where('email', 'forced_admin@example.com')->first();
    expect($user->name)->toBe('Updated Admin')
        ->and(Hash::check('new_rotated_pass!', $user->password))->toBeTrue()
        ->and(Hash::check('old_pass', $user->password))->toBeFalse();
});

test('dashboard:init-admin fails when password is not provided in cli or env', function () {
    $this->artisan('dashboard:init-admin', [
        '--email' => 'no_pass@example.com',
    ])->assertExitCode(1)
      ->expectsOutputToContain('La contraseña del administrador no puede estar vacía');

    expect(User::where('email', 'no_pass@example.com')->first())->toBeNull();
});

test('dashboard:init-admin uses environment variables when options are omitted', function () {
    config([
        'agent.admin_email' => 'env_admin@example.com',
        'agent.admin_default_password' => 'EnvPassword2026!',
        'agent.admin_name' => 'Env Admin User',
    ]);

    $this->artisan('dashboard:init-admin')
        ->assertExitCode(0)
        ->expectsOutputToContain('aprovisionado exitosamente');

    $user = User::where('email', 'env_admin@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Env Admin User')
        ->and($user->role)->toBe('admin')
        ->and(Hash::check('EnvPassword2026!', $user->password))->toBeTrue();
});
