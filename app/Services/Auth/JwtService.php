<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Services\Auth;

use App\Models\JwtRevocation;
use App\Models\User;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class JwtService
{
    protected string $secret;
    protected int $defaultTtlMinutes;
    protected string $algo;
    protected string $currentKeyId;

    /**
     * @var array<string, Key>
     */
    protected array $keys = [];

    /**
     * @param array<string, string> $additionalKeys Associative map of 'kid' => 'secret'
     */
    public function __construct(
        ?string $secret = null,
        ?int $defaultTtlMinutes = null,
        array $additionalKeys = [],
        ?string $currentKeyId = null
    ) {
        $resolvedSecret = $secret 
            ?? config('auth.jwt.secret') 
            ?? config('app.key');

        if (empty($resolvedSecret) || !is_string($resolvedSecret) || trim($resolvedSecret) === '' || $resolvedSecret === 'default_jg_dashboard_jwt_secret_32_bytes_long') {
            throw new RuntimeException(
                'JWT secret is not configured or uses an insecure default. Please set JWT_SECRET or APP_KEY in your environment configuration.'
            );
        }

        $this->secret = $this->normalizeSecret($resolvedSecret);
        $this->defaultTtlMinutes = $defaultTtlMinutes ?? (int) config('auth.jwt.ttl', 60);
        $this->algo = 'HS256';
        $this->currentKeyId = $currentKeyId ?? (string) (config('auth.jwt.key_id') ?: 'v1');

        // Register default key
        $this->keys[$this->currentKeyId] = new Key($this->secret, $this->algo);

        // Register additional rotation keys from config and constructor
        $configKeys = config('auth.jwt.keys', []);
        $mergedKeys = array_merge(is_array($configKeys) ? $configKeys : [], $additionalKeys);

        foreach ($mergedKeys as $kid => $keySecret) {
            if (is_string($kid) && is_string($keySecret) && trim($keySecret) !== '') {
                $this->registerKey($kid, $keySecret);
            }
        }
    }

    /**
     * Normalize secret stripping base64: prefix if present.
     */
    protected function normalizeSecret(string $secret): string
    {
        if (str_starts_with($secret, 'base64:')) {
            $rawBase64 = substr($secret, 7);
            $decoded = base64_decode($rawBase64, true);
            if ($decoded === false || trim($decoded) === '') {
                throw new RuntimeException(
                    'Resolved JWT secret is empty or contains an invalid base64 encoding.'
                );
            }
            return $decoded;
        }

        if (trim($secret) === '') {
            throw new RuntimeException(
                'Resolved JWT secret is empty. Please provide a valid cryptographic key.'
            );
        }

        if (strlen($secret) < 32) {
            throw new RuntimeException(
                'Resolved JWT secret is too short. HMAC-SHA256 requires a key of at least 256 bits (32 bytes).'
            );
        }

        return $secret;
    }

    /**
     * Register a rotation key indexed by key identifier (kid).
     */
    public function registerKey(string $keyId, string $secret): self
    {
        $normalized = $this->normalizeSecret($secret);
        $this->keys[$keyId] = new Key($normalized, $this->algo);

        return $this;
    }

    /**
     * Set the active signing key ID.
     */
    public function setCurrentKeyId(string $keyId): self
    {
        $this->currentKeyId = $keyId;

        return $this;
    }

    /**
     * Get active signing key ID.
     */
    public function getCurrentKeyId(): string
    {
        return $this->currentKeyId;
    }

    /**
     * Generate a signed JWT token for a given User using firebase/php-jwt.
     * Incorporates unique token identifier (jti) and key rotation header (kid).
     *
     * @param array<string, mixed> $customClaims
     */
    public function generateToken(User $user, ?int $ttlMinutes = null, array $customClaims = []): string
    {
        $ttl = $ttlMinutes ?? $this->defaultTtlMinutes;
        $now = time();
        $jti = (string) Str::uuid();

        $payload = array_merge([
            'iss' => config('app.url', 'http://localhost:8080'),
            'sub' => (string) $user->id,
            'jti' => $jti,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + ($ttl * 60),
        ], $customClaims);

        return JWT::encode(
            $payload,
            $this->secret,
            $this->algo,
            $this->currentKeyId
        );
    }

    /**
     * Verify and decode a JWT token string.
     * Checks signature, algorithm, expiration, and token blacklist/revocation.
     * Returns decoded payload array if valid, or null if invalid/tampered/expired/revoked.
     *
     * @return array<string, mixed>|null
     */
    public function verifyToken(string $token): ?array
    {
        $token = trim($token);
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $headerJson = $this->base64UrlDecode($parts[0]);
        if (!$headerJson) {
            return null;
        }

        $header = json_decode($headerJson, true);
        if (!is_array($header) || ($header['alg'] ?? null) !== $this->algo || ($header['typ'] ?? null) !== 'JWT') {
            return null;
        }

        try {
            // If token provides a kid and we have configured keys, supply keys map for rotation lookup
            // Otherwise fallback to active primary key
            $keyOrKeys = (!empty($header['kid']) && count($this->keys) > 0)
                ? $this->keys
                : ($this->keys[$this->currentKeyId] ?? new Key($this->secret, $this->algo));

            $decoded = JWT::decode($token, $keyOrKeys);
            $payload = json_decode((string) json_encode($decoded), true);

            if (!is_array($payload)) {
                return null;
            }

            // Check if token jti is revoked
            if (isset($payload['jti']) && $this->isRevoked((string) $payload['jti'])) {
                return null;
            }

            return $payload;
        } catch (Throwable) {
            return null;
        }
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

        return (int) $payload['exp'] < time();
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
     * Invalidate/revoke an issued token immediately by placing its jti on the blacklist.
     */
    public function revokeToken(string $token, ?string $reason = null): bool
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return false;
        }

        $payloadJson = $this->base64UrlDecode($parts[1]);
        if (!$payloadJson) {
            return false;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || empty($payload['jti'])) {
            return false;
        }

        $userId = isset($payload['sub']) && is_numeric($payload['sub']) ? (int) $payload['sub'] : null;
        $expiresAt = isset($payload['exp']) && is_numeric($payload['exp'])
            ? Carbon::createFromTimestamp((int) $payload['exp'])
            : null;

        return $this->revokeJti((string) $payload['jti'], $userId, $expiresAt, $reason);
    }

    /**
     * Register a jti into blacklist storage (Cache + Database).
     */
    public function revokeJti(
        string $jti,
        ?int $userId = null,
        ?\DateTimeInterface $expiresAt = null,
        ?string $reason = null
    ): bool {
        if (empty($jti)) {
            return false;
        }

        $cacheKey = "jwt:revoked:{$jti}";
        $ttlSeconds = (int) config('auth.jwt.blacklist_cache_ttl', 3600);

        try {
            Cache::put($cacheKey, true, $ttlSeconds);
        } catch (Throwable) {
            // Ignore cache failure and continue
        }

        try {
            JwtRevocation::updateOrCreate(
                ['jti' => $jti],
                [
                    'user_id' => $userId,
                    'revoked_at' => Carbon::now(),
                    'expires_at' => $expiresAt,
                    'reason' => $reason ?? 'manual_revocation',
                ]
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Check if a token jti is currently revoked in Cache or Database.
     */
    public function isRevoked(string $jti): bool
    {
        if (empty($jti)) {
            return false;
        }

        if (!config('auth.jwt.blacklist_enabled', true)) {
            return false;
        }

        $cacheKey = "jwt:revoked:{$jti}";

        try {
            if (Cache::has($cacheKey)) {
                return (bool) Cache::get($cacheKey);
            }
        } catch (Throwable) {
            // Cache issue, fallback to DB
        }

        try {
            $revoked = JwtRevocation::where('jti', $jti)->exists();
            if ($revoked) {
                try {
                    Cache::put($cacheKey, true, (int) config('auth.jwt.blacklist_cache_ttl', 3600));
                } catch (Throwable) {
                    // Ignore cache put failure
                }
            }

            return $revoked;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Base64URL encode string (RFC 7515 compliant wrapper).
     */
    public function base64UrlEncode(string $data): string
    {
        return JWT::urlsafeB64Encode($data);
    }

    /**
     * Base64URL decode string (RFC 7515 compliant wrapper).
     */
    public function base64UrlDecode(string $data): ?string
    {
        try {
            return JWT::urlsafeB64Decode($data);
        } catch (Throwable) {
            return null;
        }
    }
}
