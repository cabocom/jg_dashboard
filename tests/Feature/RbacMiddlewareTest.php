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
use App\Services\Auth\JwtService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->jwt = app(JwtService::class);

    $this->admin = User::create([
        'name' => 'Root Admin',
        'email' => 'admin_rbac@example.com',
        'password' => Hash::make('secretpass'),
        'role' => 'admin',
    ]);

    $this->operator = User::create([
        'name' => 'SRE Operator',
        'email' => 'operator_rbac@example.com',
        'password' => Hash::make('secretpass'),
        'role' => 'operator',
    ]);

    $this->viewer = User::create([
        'name' => 'ReadOnly Viewer',
        'email' => 'viewer_rbac@example.com',
        'password' => Hash::make('secretpass'),
        'role' => 'viewer',
    ]);
});

test('unauthenticated request to protected endpoint returns 401 unauthorized', function () {
    $response = $this->getJson('/api/v1/test/admin-only');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Token de autenticación no provisto',
        ]);
});

test('request with invalid or tampered token returns 401 unauthorized', function () {
    $response = $this->withHeader('Authorization', 'Bearer invalid.tampered.token')
        ->getJson('/api/v1/test/admin-only');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Token de autenticación inválido o expirado',
        ]);
});

test('request with expired token returns 401 unauthorized', function () {
    $expiredToken = $this->jwt->generateToken($this->admin, -10);

    $response = $this->withHeader('Authorization', "Bearer {$expiredToken}")
        ->getJson('/api/v1/test/admin-only');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Token de autenticación inválido o expirado',
        ]);
});

test('viewer role receives 403 forbidden on admin-only endpoint', function () {
    $token = $this->jwt->generateToken($this->viewer);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/test/admin-only');

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'Forbidden',
            'message' => 'Acceso denegado: rol insuficiente',
        ]);
});

test('operator role receives 403 forbidden on admin-only endpoint', function () {
    $token = $this->jwt->generateToken($this->operator);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/test/admin-only');

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'Forbidden',
            'message' => 'Acceso denegado: rol insuficiente',
        ]);
});

test('admin role accesses admin-only endpoint successfully', function () {
    $token = $this->jwt->generateToken($this->admin);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/test/admin-only');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
            'message' => 'Admin resource accessed',
        ]);
});

test('operator role accesses operator endpoint successfully', function () {
    $token = $this->jwt->generateToken($this->operator);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/test/operator-only');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
            'message' => 'Operator resource accessed',
        ]);
});

test('admin role accesses operator endpoint through role hierarchy', function () {
    $token = $this->jwt->generateToken($this->admin);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/test/operator-only');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
            'message' => 'Operator resource accessed',
        ]);
});

test('viewer role receives 403 forbidden on operator endpoint', function () {
    $token = $this->jwt->generateToken($this->viewer);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/test/operator-only');

    $response->assertStatus(403);
});

test('viewer role accesses viewer endpoint successfully', function () {
    $token = $this->jwt->generateToken($this->viewer);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/test/viewer-only');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
            'message' => 'Viewer resource accessed',
        ]);
});

test('permission middleware enforces permissions according to rbac matrix', function () {
    $viewerToken = $this->jwt->generateToken($this->viewer);
    $adminToken = $this->jwt->generateToken($this->admin);

    // Viewer denied
    $this->withHeader('Authorization', "Bearer {$viewerToken}")
        ->getJson('/api/v1/test/permission-manage')
        ->assertStatus(403)
        ->assertJson([
            'error' => 'Forbidden',
            'message' => 'Acceso denegado: permiso insuficiente',
        ]);

    // Admin allowed
    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/api/v1/test/permission-manage')
        ->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
            'message' => 'Agent manage permission granted',
        ]);
});
