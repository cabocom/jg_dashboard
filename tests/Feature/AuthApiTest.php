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

    // Also verify unversioned /api/auth/login returns 404 (CWE-668)
    $aliasResponse = $this->postJson('/api/auth/login', [
        'email' => 'api_admin@example.com',
        'password' => 'AdminSecretPass!',
    ]);
    $aliasResponse->assertStatus(404);
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

test('api auth me returns 401 when unauthenticated', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Token de autenticación no provisto',
        ]);
});

test('api auth me returns user profile, role and permissions with valid token', function () {
    $operator = User::create([
        'name' => 'SRE Operator One',
        'email' => 'sre_op@example.com',
        'password' => Hash::make('password123'),
        'role' => 'operator',
    ]);

    // 1. Authenticate via login
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'sre_op@example.com',
        'password' => 'password123',
    ]);
    $loginResponse->assertStatus(200);
    $token = $loginResponse->json('access_token');

    // 2. Query /api/v1/auth/me
    $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me');

    $meResponse->assertStatus(200)
        ->assertJsonStructure([
            'id',
            'name',
            'email',
            'role',
            'permissions',
            'created_at',
        ]);

    expect($meResponse->json('id'))->toBe($operator->id)
        ->and($meResponse->json('email'))->toBe('sre_op@example.com')
        ->and($meResponse->json('role'))->toBe('operator')
        ->and($meResponse->json('permissions'))->toContain('hitl:approve', 'tasks:trigger', 'dashboard:view')
        ->and($meResponse->json('permissions'))->not->toContain('agents:manage');

    // 3. Also verify unversioned /api/auth/me returns 404 (CWE-668)
    $aliasMe = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/auth/me');
    $aliasMe->assertStatus(404);
});

test('api auth me returns admin full permissions', function () {
    $admin = User::create([
        'name' => 'Super Admin',
        'email' => 'super_admin@example.com',
        'password' => Hash::make('adminpass123'),
        'role' => 'admin',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'super_admin@example.com',
        'password' => 'adminpass123',
    ]);
    $token = $loginResponse->json('access_token');

    $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me');

    $meResponse->assertStatus(200);
    $permissions = $meResponse->json('permissions');

    expect($permissions)->toContain('agents:manage', 'agents:register', 'hitl:approve', 'audit:view');
});

test('api logout endpoint returns 200 with success message and revokes token', function () {
    $user = User::create([
        'name' => 'Logout User',
        'email' => 'logout@example.com',
        'password' => Hash::make('secretpass'),
        'role' => 'viewer',
    ]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'logout@example.com',
        'password' => 'secretpass',
    ]);
    $token = $login->json('access_token');

    // 1. Verify token works before logout
    $preLogout = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me');
    $preLogout->assertStatus(200);

    // 2. Perform logout
    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Sesión cerrada exitosamente',
        ]);

    // 3. Subsequent request with the same token must be rejected with 401 Unauthorized
    $postLogout = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me');

    $postLogout->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Token de autenticación inválido o expirado',
        ]);
});
