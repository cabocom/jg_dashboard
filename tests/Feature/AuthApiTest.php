<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('api login returns 200 with jwt and user details on valid credentials', function () {
    $user = User::create([
        'name' => 'Admin API User',
        'email' => 'api_admin@example.com',
        'password' => Hash::make('AdminSecretPass!'),
        'role' => 'admin',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'api_admin@example.com',
        'password' => 'AdminSecretPass!',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'user' => [
                'id',
                'name',
                'email',
                'role',
            ],
        ]);

    expect($response->json('token_type'))->toBe('Bearer')
        ->and($response->json('user.email'))->toBe('api_admin@example.com')
        ->and($response->json('user.role'))->toBe('admin');

    // Also verify alias /api/auth/login
    $aliasResponse = $this->postJson('/api/auth/login', [
        'email' => 'api_admin@example.com',
        'password' => 'AdminSecretPass!',
    ]);
    $aliasResponse->assertStatus(200);
});

test('api login returns 401 on invalid credentials', function () {
    User::create([
        'name' => 'Operator Bob',
        'email' => 'bob@example.com',
        'password' => Hash::make('CorrectPassword123'),
        'role' => 'operator',
    ]);

    // Wrong password
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'WrongPassword!',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Credenciales inválidas',
        ]);

    // Non-existent email
    $nonExistent = $this->postJson('/api/v1/auth/login', [
        'email' => 'unknown@example.com',
        'password' => 'AnyPassword!',
    ]);

    $nonExistent->assertStatus(401);
});

test('api login returns 422 on validation failure', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});
