<?php

namespace App\Services\Auth;

use App\Models\User;
use InvalidArgumentException;
use RuntimeException;

class JwtService
{
    protected string $secret;
    protected int $defaultTtlMinutes;
    protected string $algo;

    public function __construct(?string $secret = null, ?int $defaultTtlMinutes = null)
    {
        $resolvedSecret = $secret 
            ?? config('auth.jwt.secret') 
            ?? config('app.key');

        if (empty($resolvedSecret) || !is_string($resolvedSecret) || trim($resolvedSecret) === '' || $resolvedSecret === 'default_jg_dashboard_jwt_secret_32_bytes_long') {
            throw new RuntimeException(
                'JWT secret is not configured or uses an insecure default. Please set JWT_SECRET or APP_KEY in your environment configuration.'
            );
        }

        // Strip base64: prefix if present (common in Laravel APP_KEY)
        if (str_starts_with($resolvedSecret, 'base64:')) {
            $rawBase64 = substr($resolvedSecret, 7);
            $decoded = base64_decode($rawBase64, true);
            if ($decoded === false || trim($decoded) === '') {
                throw new RuntimeException(
                    'Resolved JWT secret is empty or contains an invalid base64 encoding.'
                );
            }
            $this->secret = $decoded;
        } else {
            $this->secret = $resolvedSecret;
        }

        if (empty($this->secret) || trim($this->secret) === '') {
            throw new RuntimeException(
                'Resolved JWT secret is empty. Please provide a valid cryptographic key.'
            );
        }

        $this->defaultTtlMinutes = $defaultTtlMinutes ?? (int) config('auth.jwt.ttl', 60);
        $this->algo = 'HS256';
    }

    /**
     * Generate a signed JWT token for a given User.
     *
     * @param array<string, mixed> $customClaims
     */
    public function generateToken(User $user, ?int $ttlMinutes = null, array $customClaims = []): string
    {
        $ttl = $ttlMinutes ?? $this->defaultTtlMinutes;
        $now = time();

        $header = [
            'typ' => 'JWT',
            'alg' => $this->algo,
        ];

        $payload = array_merge([
            'iss' => config('app.url', 'http://localhost:8080'),
            'sub' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + ($ttl * 60),
        ], $customClaims);

        $encodedHeader = $this->base64UrlEncode((string) json_encode($header));
        $encodedPayload = $this->base64UrlEncode((string) json_encode($payload));

        $signature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $this->secret, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        return "{$encodedHeader}.{$encodedPayload}.{$encodedSignature}";
    }

    /**
     * Verify and decode a JWT token string.
     * Returns decoded payload array if valid, or null if invalid/tampered/expired.
     *
     * @return array<string, mixed>|null
     */
    public function verifyToken(string $token): ?array
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        // Verify header
        $headerJson = $this->base64UrlDecode($encodedHeader);
        if (!$headerJson) {
            return null;
        }

        $header = json_decode($headerJson, true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256' || ($header['typ'] ?? null) !== 'JWT') {
            return null;
        }

        // Verify signature
        $expectedSignature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $this->secret, true);
        $actualSignature = $this->base64UrlDecode($encodedSignature);

        if (!$actualSignature || !hash_equals($expectedSignature, $actualSignature)) {
            return null;
        }

        // Decode payload
        $payloadJson = $this->base64UrlDecode($encodedPayload);
        if (!$payloadJson) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        $now = time();

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < $now) {
            return null;
        }

        // Check not before
        if (isset($payload['nbf']) && $payload['nbf'] > $now) {
            return null;
        }

        return $payload;
    }

    /**
     * Check if a token is expired specifically.
     */
    public function isExpired(string $token): bool
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return true;
        }

        $payloadJson = $this->base64UrlDecode($parts[1]);
        if (!$payloadJson) {
            return true;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || !isset($payload['exp'])) {
            return true;
        }

        return $payload['exp'] < time();
    }

    /**
     * Extract User model from valid JWT token.
     */
    public function getUserFromToken(string $token): ?User
    {
        $payload = $this->verifyToken($token);
        if (!$payload || !isset($payload['sub'])) {
            return null;
        }

        return User::find($payload['sub']);
    }

    /**
     * Base64URL encode string.
     */
    public function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64URL decode string.
     */
    public function base64UrlDecode(string $data): ?string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded !== false ? $decoded : null;
    }
}
