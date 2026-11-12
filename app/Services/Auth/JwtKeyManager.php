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

use Firebase\JWT\Key;
use RuntimeException;

class JwtKeyManager
{
    protected string $secret;

    protected string $currentKeyId;

    /**
     * @var array<string, Key|string>
     */
    protected array $keys = [];

    protected string $algo = 'HS256';

    /**
     * @param  array<string, string>  $additionalKeys
     */
    public function __construct(
        ?string $secret = null,
        array $additionalKeys = [],
        ?string $currentKeyId = null
    ) {
        $resolvedSecret = $secret ?? config('auth.jwt.secret') ?? config('app.key');
        if (empty($resolvedSecret) || ! is_string($resolvedSecret) || trim($resolvedSecret) === '' || $resolvedSecret === 'default_jg_dashboard_jwt_secret_32_bytes_long') {
            throw new RuntimeException(
                'JWT secret is not configured or uses an insecure default. Please set JWT_SECRET or APP_KEY in your environment configuration.'
            );
        }

        $this->secret = $this->normalizeSecret($resolvedSecret);
        $this->currentKeyId = $currentKeyId ?? (string) (config('auth.jwt.key_id') ?: 'v1');

        $this->keys[$this->currentKeyId] = class_exists(Key::class)
            ? new Key($this->secret, $this->algo)
            : $this->secret;

        $configKeys = config('auth.jwt.keys', []);
        $mergedKeys = array_merge(is_array($configKeys) ? $configKeys : [], $additionalKeys);

        foreach ($mergedKeys as $kid => $keySecret) {
            if (is_string($kid) && is_string($keySecret) && trim($keySecret) !== '') {
                $this->registerKey($kid, $keySecret);
            }
        }
    }

    /**
     * Normalize secret stripping base64: prefix if present and validating 256-bit requirement.
     */
    public function normalizeSecret(string $secret): string
    {
        if (str_starts_with($secret, 'base64:')) {
            $rawBase64 = substr($secret, 7);
            $decoded = base64_decode($rawBase64, true);
            if ($decoded === false || trim($decoded) === '') {
                throw new RuntimeException('Resolved JWT secret is empty or contains an invalid base64 encoding.');
            }

            return $decoded;
        }

        if (trim($secret) === '') {
            throw new RuntimeException('Resolved JWT secret is empty. Please provide a valid cryptographic key.');
        }

        if (strlen($secret) < 32) {
            throw new RuntimeException('Resolved JWT secret is too short. HMAC-SHA256 requires a key of at least 256 bits (32 bytes).');
        }

        return $secret;
    }

    public function registerKey(string $keyId, string $secret): self
    {
        $normalized = $this->normalizeSecret($secret);
        $this->keys[$keyId] = class_exists(Key::class)
            ? new Key($normalized, $this->algo)
            : $normalized;

        return $this;
    }

    public function setCurrentKeyId(string $keyId): self
    {
        $this->currentKeyId = $keyId;

        return $this;
    }

    public function getCurrentKeyId(): string
    {
        return $this->currentKeyId;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getAlgo(): string
    {
        return $this->algo;
    }

    /**
     * @return array<string, Key|string>
     */
    public function getKeys(): array
    {
        return $this->keys;
    }

    public function resolveKeyForVerification(?string $kid = null): Key|string
    {
        if (! empty($kid) && isset($this->keys[$kid])) {
            return $this->keys[$kid];
        }

        return $this->keys[$this->currentKeyId] ?? $this->secret;
    }
}

