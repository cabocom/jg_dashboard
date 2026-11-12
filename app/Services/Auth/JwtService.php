<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Services\Auth;

use App\Models\User;
use DateTimeInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use Throwable;

class JwtService
{
    protected int $defaultTtlMinutes;

    protected JwtKeyManager $keyManager;

    protected JwtBlacklistService $blacklist;

    /**
     * @param  array<string, string>  $additionalKeys  Associative map of 'kid' => 'secret'
     */
    public function __construct(
        ?string $secret = null,
        ?int $defaultTtlMinutes = null,
        array $additionalKeys = [],
        ?string $currentKeyId = null,
        ?JwtBlacklistService $blacklist = null,
        ?JwtKeyManager $keyManager = null
    ) {
        $this->blacklist = $blacklist ?? new JwtBlacklistService;
        $this->keyManager = $keyManager ?? new JwtKeyManager($secret, $additionalKeys, $currentKeyId);
        $this->defaultTtlMinutes = $defaultTtlMinutes ?? (int) config('auth.jwt.ttl', 60);
    }

    public function registerKey(string $keyId, string $secret): self
    {
        $this->keyManager->registerKey($keyId, $secret);

        return $this;
    }

    public function setCurrentKeyId(string $keyId): self
    {
        $this->keyManager->setCurrentKeyId($keyId);

        return $this;
    }

    public function getCurrentKeyId(): string
    {
        return $this->keyManager->getCurrentKeyId();
    }

    /**
     * Generate a signed JWT token for a given User using firebase/php-jwt.
     *
     * @param  array<string, mixed>  $customClaims
     */
    public function generateToken(User $user, ?int $ttlMinutes = null, array $customClaims = []): string
    {
        $ttl = $ttlMinutes ?? $this->defaultTtlMinutes;
        $now = time();
        $payload = array_merge([
            'iss' => config('app.url', 'http://localhost:8080'),
            'sub' => (string) $user->id,
            'jti' => (string) Str::uuid(),
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + ($ttl * 60),
        ], $customClaims);

        if (class_exists(JWT::class)) {
            return JWT::encode(
                $payload,
                $this->keyManager->getSecret(),
                $this->keyManager->getAlgo(),
                $this->keyManager->getCurrentKeyId()
            );
        }

        return $this->encodeManually($payload);
    }

    /**
     * Manual fallback JWT encoder when library is unavailable.
     */
    protected function encodeManually(array $payload): string
    {
        $header = ['typ' => 'JWT', 'alg' => $this->keyManager->getAlgo(), 'kid' => $this->keyManager->getCurrentKeyId()];
        $segments = [
            $this->base64UrlEncode((string) json_encode($header)),
            $this->base64UrlEncode((string) json_encode($payload)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), $this->keyManager->getSecret(), true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * Verify and decode a JWT token string.
     *
     * @return array<string, mixed>|null
     */
    public function verifyToken(string $token): ?array
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return null;
        }

        $header = $this->parseTokenHeader($parts[0]);
        if (! $header) {
            return null;
        }

        try {
            $payload = class_exists(JWT::class)
                ? $this->decodeWithFirebaseJwt($token, $header)
                : $this->decodeManually($parts, $header);

            if (! is_array($payload) || (isset($payload['jti']) && $this->isRevoked((string) $payload['jti']))) {
                return null;
            }

            return $payload;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Parse and validate token header segment.
     */
    protected function parseTokenHeader(string $headerSegment): ?array
    {
        $headerJson = $this->base64UrlDecode($headerSegment);
        if (! $headerJson) {
            return null;
        }

        $header = json_decode($headerJson, true);
        if (! is_array($header) || ($header['alg'] ?? null) !== $this->keyManager->getAlgo() || ($header['typ'] ?? null) !== 'JWT') {
            return null;
        }

        return $header;
    }

    /**
     * Decode token using Firebase JWT library.
     */
    protected function decodeWithFirebaseJwt(string $token, array $header): ?array
    {
        $allKeys = $this->keyManager->getKeys();
        $keyOrKeys = (! empty($header['kid']) && count($allKeys) > 0)
            ? $allKeys
            : $this->keyManager->resolveKeyForVerification($header['kid'] ?? null);

        $decoded = JWT::decode($token, $keyOrKeys);

        return json_decode((string) json_encode($decoded), true);
    }

    /**
     * Manual fallback token decoder and signature verification.
     */
    protected function decodeManually(array $parts, array $header): ?array
    {
        $rawKey = $this->keyManager->resolveKeyForVerification($header['kid'] ?? null);
        $secretStr = is_string($rawKey) ? $rawKey : ($rawKey instanceof Key ? $this->keyManager->getSecret() : $this->keyManager->getSecret());

        $expectedSig = hash_hmac('sha256', $parts[0].'.'.$parts[1], $secretStr, true);
        $providedSig = $this->base64UrlDecode($parts[2]);
        if ($providedSig === null || ! hash_equals($expectedSig, $providedSig)) {
            return null;
        }

        $payloadJson = $this->base64UrlDecode($parts[1]);
        $payload = $payloadJson ? json_decode($payloadJson, true) : null;
        if (! is_array($payload)) {
            return null;
        }

        $now = time();
        if ((isset($payload['nbf']) && (int) $payload['nbf'] > $now) || (isset($payload['exp']) && (int) $payload['exp'] < $now)) {
            return null;
        }

        return $payload;
    }

    public function isExpired(string $token): bool
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return true;
        }

        $payloadJson = $this->base64UrlDecode($parts[1]);
        if (! $payloadJson) {
            return true;
        }

        $payload = json_decode($payloadJson, true);
        if (! is_array($payload) || ! isset($payload['exp'])) {
            return true;
        }

        return (int) $payload['exp'] < time();
    }

    public function getUserFromToken(string $token): ?User
    {
        $payload = $this->verifyToken($token);
        if (! $payload || ! isset($payload['sub'])) {
            return null;
        }

        return User::find($payload['sub']);
    }

    public function revokeToken(string $token, ?string $reason = null): bool
    {
        return $this->blacklist->revokeToken($token, $reason, fn (string $seg) => $this->base64UrlDecode($seg));
    }

    public function revokeJti(
        string $jti,
        ?int $userId = null,
        ?DateTimeInterface $expiresAt = null,
        ?string $reason = null
    ): bool {
        return $this->blacklist->revokeJti($jti, $userId, $expiresAt, $reason);
    }

    public function isRevoked(string $jti): bool
    {
        return $this->blacklist->isRevoked($jti);
    }

    public function base64UrlEncode(string $data): string
    {
        if (class_exists(JWT::class)) {
            return JWT::urlsafeB64Encode($data);
        }

        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function base64UrlDecode(string $data): ?string
    {
        try {
            if (class_exists(JWT::class)) {
                return JWT::urlsafeB64Decode($data);
            }
            $remainder = strlen($data) % 4;
            if ($remainder) {
                $data .= str_repeat('=', 4 - $remainder);
            }
            $decoded = base64_decode(strtr($data, '-_', '+/'), true);

            return $decoded === false ? null : $decoded;
        } catch (Throwable) {
            return null;
        }
    }
}
