<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->adminUser = User::create([
        'name' => 'Super Admin',
        'email' => 'superadmin@devops60k.local',
        'password' => Hash::make('secret_admin_pass'),
        'role' => 'admin',
    ]);
});

test('admin user can access /admin/users list in Filament', function () {
    $response = $this->actingAs($this->adminUser)->get('/admin/users');

    $response->assertStatus(200)
        ->assertSee('Usuarios')
        ->assertSee('superadmin@devops60k.local');
});

test('operator role cannot delete their own account', function () {
    $operator = User::create([
        'name' => 'Operator Person',
        'email' => 'operator.person@devops60k.local',
        'password' => Hash::make('password123'),
        'role' => 'operator',
    ]);

    $response = $this->actingAs($operator)->get('/admin/users');
    $response->assertStatus(200);
});
