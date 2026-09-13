<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Operador Inicial',
        'email' => 'operador@devops60k.local',
        'password' => Hash::make('password123'),
        'role' => 'operator',
    ]);
});

test('GET /console/profile returns authenticated user profile info', function () {
    $response = $this->actingAs($this->user)->getJson('/console/profile');

    $response->assertStatus(200)
        ->assertJson([
            'user' => [
                'id' => $this->user->id,
                'name' => 'Operador Inicial',
                'email' => 'operador@devops60k.local',
                'role' => 'operator',
            ],
        ]);
});

test('POST /console/profile updates user name and email successfully', function () {
    $response = $this->actingAs($this->user)->postJson('/console/profile', [
        'name' => 'Operador Modificado',
        'email' => 'operador.nuevo@devops60k.local',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'user' => [
                'name' => 'Operador Modificado',
                'email' => 'operador.nuevo@devops60k.local',
            ],
        ]);

    $this->user->refresh();
    expect($this->user->name)->toBe('Operador Modificado')
        ->and($this->user->email)->toBe('operador.nuevo@devops60k.local');
});

test('POST /console/profile updates password when current password is valid', function () {
    $response = $this->actingAs($this->user)->postJson('/console/profile', [
        'name' => 'Operador Inicial',
        'email' => 'operador@devops60k.local',
        'current_password' => 'password123',
        'new_password' => 'new-secret-999',
        'new_password_confirmation' => 'new-secret-999',
    ]);

    $response->assertStatus(200);

    $this->user->refresh();
    expect(Hash::check('new-secret-999', $this->user->password))->toBeTrue();
});

test('POST /console/profile fails when current password is invalid', function () {
    $response = $this->actingAs($this->user)->postJson('/console/profile', [
        'name' => 'Operador Inicial',
        'email' => 'operador@devops60k.local',
        'current_password' => 'wrong-pass',
        'new_password' => 'new-secret-999',
        'new_password_confirmation' => 'new-secret-999',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'error' => 'InvalidCurrentPassword',
        ]);
});

test('GET /console renders theme switcher and user profile modal triggers', function () {
    $response = $this->actingAs($this->user)->get('/console');

    $response->assertStatus(200)
        ->assertSee('id="btn-toggle-theme"', false)
        ->assertSee('id="theme-dropdown-menu"', false)
        ->assertSee('id="btn-user-profile"', false)
        ->assertSee('id="modal-user-profile"', false)
        ->assertSee('Operador Inicial');
});
