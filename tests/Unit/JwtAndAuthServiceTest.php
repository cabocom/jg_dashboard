<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

use App\Models\JwtRevocation;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\JwtService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

test('jwt service generates valid rfc 7519 token with user claims, kid and jti', function () {
    $jwt = new JwtService('test_secret_key_1234567890_abcdef_32_bytes!');
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

    // Verify header includes kid and typ
    $header = json_decode($jwt->base64UrlDecode($parts[0]), true);
    expect($header)->toBeArray()
        ->and($header['alg'])->toBe('HS256')
        ->and($header['typ'])->toBe('JWT')
        ->and($header['kid'])->toBe('v1');

    $payload = $jwt->verifyToken($token);
    expect($payload)->not->toBeNull()
        ->and($payload['sub'])->toBe((string) $user->id)
        ->and($payload['email'])->toBe('john@example.com')
        ->and($payload['role'])->toBe('operator')
        ->and($payload['name'])->toBe('John Operator')
        ->and($payload['jti'])->toBeString()
        ->and(strlen($payload['jti']))->toBe(36)
        ->and($payload['exp'])->toBeGreaterThan(time());

    $retrievedUser = $jwt->getUserFromToken($token);
    expect($retrievedUser)->not->toBeNull()
        ->and($retrievedUser->id)->toBe($user->id);
});

test('jwt service rejects tampered tokens and signatures', function () {
    $jwt = new JwtService('secret_key_a_with_at_least_32_bytes_length!!');
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
    $otherJwt = new JwtService('different_secret_key_b_32_bytes_long_ok!');
    expect($otherJwt->verifyToken($token))->toBeNull();
});

test('jwt service rejects tokens with alg none or invalid headers', function () {
    $jwt = new JwtService('secret_key_test_32_bytes_minimum_length!');
    $badHeader = $jwt->base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'none']));
    $payload = $jwt->base64UrlEncode((string) json_encode(['sub' => '1', 'exp' => time() + 3600]));
    $fakeToken = "{$badHeader}.{$payload}.";

    expect($jwt->verifyToken($fakeToken))->toBeNull();
});

test('jwt service rejects expired tokens', function () {
    $jwt = new JwtService('secret_key_test_32_bytes_minimum_length!');
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
        ->and($verifiedPayload['sub'])->toBe((string) $user->id)
        ->and($verifiedPayload['jti'])->toBeString();
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

test('jwt service throws runtime exception when secret is shorter than 32 bytes', function () {
    expect(fn () => new JwtService('short_secret_under_32_bytes!'))
        ->toThrow(RuntimeException::class, 'Resolved JWT secret is too short');
});

test('jwt service correctly falls back to app.key when jwt.secret is null', function () {
    // 32+ bytes string base64 encoded
    $encodedKey = base64_encode('test_key_for_app_key_fallback_32_bytes_ok!');
    config([
        'auth.jwt.secret' => null,
        'app.key' => "base64:{$encodedKey}",
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

test('jwt service supports key rotation via kid lookup', function () {
    $oldKey = 'old_secret_key_rotation_v1_32_bytes!';
    $newKey = 'new_secret_key_rotation_v2_32_bytes!';

    // Service v1 generates token with keyId 'v1'
    $jwtV1 = new JwtService($oldKey, null, [], 'v1');
    $user = User::create([
        'name' => 'Rotation User',
        'email' => 'rotation@example.com',
        'password' => 'secret123',
        'role' => 'operator',
    ]);
    $tokenV1 = $jwtV1->generateToken($user);

    // Service v2 with rotated active key 'v2', but registering 'v1' in the rotation keyring
    $jwtV2 = new JwtService($newKey, null, ['v1' => $oldKey], 'v2');
    $tokenV2 = $jwtV2->generateToken($user);

    // Service v2 can verify both old token (kid=v1) and new token (kid=v2)
    $payloadV1 = $jwtV2->verifyToken($tokenV1);
    $payloadV2 = $jwtV2->verifyToken($tokenV2);

    expect($payloadV1)->not->toBeNull()
        ->and($payloadV1['sub'])->toBe((string) $user->id)
        ->and($payloadV2)->not->toBeNull()
        ->and($payloadV2['sub'])->toBe((string) $user->id);

    // If a token comes with an unknown kid, verification fails cleanly
    $unknownKeyJwt = new JwtService('another_random_secret_32_bytes_ok!', null, [], 'v99');
    $unknownToken = $unknownKeyJwt->generateToken($user);
    expect($jwtV2->verifyToken($unknownToken))->toBeNull();
});

test('jwt service supports immediate token revocation via blacklist', function () {
    $jwt = new JwtService('test_secret_key_1234567890_abcdef_32_bytes!');
    $user = User::create([
        'name' => 'Revoke User',
        'email' => 'revoke@example.com',
        'password' => 'secret123',
        'role' => 'operator',
    ]);

    $token = $jwt->generateToken($user, 60);
    $payload = $jwt->verifyToken($token);
    expect($payload)->not->toBeNull();

    $jti = $payload['jti'];
    expect($jwt->isRevoked($jti))->toBeFalse();

    // Revoke token
    $revoked = $jwt->revokeToken($token, 'compromised');
    expect($revoked)->toBeTrue()
        ->and($jwt->isRevoked($jti))->toBeTrue();

    // Database record created
    $dbRecord = JwtRevocation::where('jti', $jti)->first();
    expect($dbRecord)->not->toBeNull()
        ->and($dbRecord->user_id)->toBe($user->id)
        ->and($dbRecord->reason)->toBe('compromised');

    // Subsequent token verification fails
    expect($jwt->verifyToken($token))->toBeNull()
        ->and($jwt->getUserFromToken($token))->toBeNull();
});

test('jwt blacklist service acts in fail-closed mode when database query throws exception', function () {
    $blacklist = new \App\Services\Auth\JwtBlacklistService();
    $jti = 'test-jti-fail-closed-9999';

    \Illuminate\Support\Facades\Cache::forget("jwt:revoked:{$jti}");

    // Simular caída o indisponibilidad de la tabla dashboard_jwt_revocations
    \Illuminate\Support\Facades\Schema::dropIfExists('dashboard_jwt_revocations');

    // Con la tabla rota y sin caché, isRevoked debe actuar en modo Fail-Closed (denegar token retornando true)
    expect($blacklist->isRevoked($jti))->toBeTrue();

    // Restaurar la tabla para la ejecución posterior de la suite
    \Illuminate\Support\Facades\Schema::create('dashboard_jwt_revocations', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->string('jti', 64)->unique()->index();
        $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('revoked_at')->useCurrent();
        $table->timestamp('expires_at')->nullable()->index();
        $table->string('reason', 128)->nullable();
        $table->timestamps();
    });
});
