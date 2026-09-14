<?php

use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\JwtService;
use Illuminate\Support\Facades\Hash;

test('jwt service generates valid rfc 7519 token with user claims', function () {
    $jwt = new JwtService('test_secret_key_1234567890_abcdef');
    $user = User::create([
        'name' => 'John Operator',
        'email' => 'john@example.com',
        'password' => 'secret123',
        'role' => 'operator',
    ]);

    $token = $jwt->generateToken($user, 60);

    expect($token)->toBeString();
    $parts = explode('.', $token);
    expect($parts)->toHaveCount(3);

    $payload = $jwt->verifyToken($token);
    expect($payload)->not->toBeNull()
        ->and($payload['sub'])->toBe((string) $user->id)
        ->and($payload['email'])->toBe('john@example.com')
        ->and($payload['role'])->toBe('operator')
        ->and($payload['name'])->toBe('John Operator')
        ->and($payload['exp'])->toBeGreaterThan(time());

    $retrievedUser = $jwt->getUserFromToken($token);
    expect($retrievedUser)->not->toBeNull()
        ->and($retrievedUser->id)->toBe($user->id);
});

test('jwt service rejects tampered tokens and signatures', function () {
    $jwt = new JwtService('secret_key_a');
    $user = User::create([
        'name' => 'Alice Admin',
        'email' => 'alice@example.com',
        'password' => 'secret123',
        'role' => 'admin',
    ]);

    $token = $jwt->generateToken($user, 60);
    $parts = explode('.', $token);

    // Tamper payload (e.g. elevate role to superadmin)
    $tamperedPayload = json_decode($jwt->base64UrlDecode($parts[1]), true);
    $tamperedPayload['role'] = 'superadmin';
    $tamperedEncodedPayload = $jwt->base64UrlEncode((string) json_encode($tamperedPayload));

    $tamperedToken = "{$parts[0]}.{$tamperedEncodedPayload}.{$parts[2]}";

    expect($jwt->verifyToken($tamperedToken))->toBeNull();

    // Verification with different secret fails
    $otherJwt = new JwtService('different_secret_key_b');
    expect($otherJwt->verifyToken($token))->toBeNull();
});

test('jwt service rejects tokens with alg none or invalid headers', function () {
    $jwt = new JwtService('secret');
    $badHeader = $jwt->base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'none']));
    $payload = $jwt->base64UrlEncode((string) json_encode(['sub' => '1', 'exp' => time() + 3600]));
    $fakeToken = "{$badHeader}.{$payload}.";

    expect($jwt->verifyToken($fakeToken))->toBeNull();
});

test('jwt service rejects expired tokens', function () {
    $jwt = new JwtService('secret');
    $user = User::create([
        'name' => 'Eve Expiring',
        'email' => 'eve@example.com',
        'password' => 'secret123',
        'role' => 'viewer',
    ]);

    // Token expired 10 minutes ago
    $expiredToken = $jwt->generateToken($user, -10);

    expect($jwt->isExpired($expiredToken))->toBeTrue()
        ->and($jwt->verifyToken($expiredToken))->toBeNull()
        ->and($jwt->getUserFromToken($expiredToken))->toBeNull();
});

test('auth service attempt succeeds with valid credentials', function () {
    $user = User::create([
        'name' => 'Valid User',
        'email' => 'valid@example.com',
        'password' => Hash::make('mypassword123'),
        'role' => 'operator',
    ]);

    $authService = app(AuthService::class);
    $result = $authService->attempt('valid@example.com', 'mypassword123');

    expect($result)->not->toBeNull()
        ->and($result)->toHaveKeys(['access_token', 'token_type', 'expires_in', 'user'])
        ->and($result['token_type'])->toBe('Bearer')
        ->and($result['user']['email'])->toBe('valid@example.com')
        ->and($result['user']['role'])->toBe('operator');

    $verifiedPayload = $authService->jwt()->verifyToken($result['access_token']);
    expect($verifiedPayload)->not->toBeNull()
        ->and($verifiedPayload['sub'])->toBe((string) $user->id);
});

test('auth service attempt rejects invalid password and non-existent email', function () {
    User::create([
        'name' => 'User One',
        'email' => 'user1@example.com',
        'password' => Hash::make('correct_pass'),
        'role' => 'operator',
    ]);

    $authService = app(AuthService::class);

    // Wrong password
    expect($authService->attempt('user1@example.com', 'wrong_pass'))->toBeNull();

    // Non-existent email
    expect($authService->attempt('ghost@example.com', 'any_pass'))->toBeNull();
});

test('auth service hashPassword and verifyPassword helpers', function () {
    $authService = app(AuthService::class);

    $hash = $authService->hashPassword('test_pass_secure');
    expect($hash)->not->toBe('test_pass_secure')
        ->and($authService->verifyPassword('test_pass_secure', $hash))->toBeTrue()
        ->and($authService->verifyPassword('wrong_guess', $hash))->toBeFalse();
});

test('jwt service throws runtime exception when no secret is configured', function () {
    config([
        'auth.jwt.secret' => null,
        'app.key' => null,
    ]);

    expect(fn () => new JwtService())
        ->toThrow(RuntimeException::class, 'JWT secret is not configured or uses an insecure default');
});

test('jwt service throws runtime exception when secret is empty or whitespace only', function () {
    config([
        'auth.jwt.secret' => '   ',
        'app.key' => null,
    ]);

    expect(fn () => new JwtService())
        ->toThrow(RuntimeException::class, 'JWT secret is not configured or uses an insecure default');

    expect(fn () => new JwtService(''))
        ->toThrow(RuntimeException::class, 'JWT secret is not configured or uses an insecure default');
});

test('jwt service throws runtime exception when secret is hardcoded default secret', function () {
    config([
        'auth.jwt.secret' => 'default_jg_dashboard_jwt_secret_32_bytes_long',
        'app.key' => null,
    ]);

    expect(fn () => new JwtService())
        ->toThrow(RuntimeException::class, 'JWT secret is not configured or uses an insecure default');

    expect(fn () => new JwtService('default_jg_dashboard_jwt_secret_32_bytes_long'))
        ->toThrow(RuntimeException::class, 'JWT secret is not configured or uses an insecure default');
});

test('jwt service throws runtime exception when base64 secret resolves to empty', function () {
    expect(fn () => new JwtService('base64:'))
        ->toThrow(RuntimeException::class, 'Resolved JWT secret is empty');
});

test('jwt service correctly falls back to app.key when jwt.secret is null', function () {
    config([
        'auth.jwt.secret' => null,
        'app.key' => 'base64:dGVzdF9rZXlfZm9yX2FwcF9rZXlfZmFsbGJhY2s=',
    ]);

    $jwt = new JwtService();
    $user = User::create([
        'name' => 'Fallback User',
        'email' => 'fallback@example.com',
        'password' => 'secret123',
        'role' => 'operator',
    ]);

    $token = $jwt->generateToken($user);
    $payload = $jwt->verifyToken($token);

    expect($payload)->not->toBeNull()
        ->and($payload['email'])->toBe('fallback@example.com');
});

