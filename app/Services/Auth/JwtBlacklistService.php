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

use App\Models\JwtRevocation;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Throwable;

class JwtBlacklistService
{
    /**
     * Invalidate/revoke an issued token by decoding its jti and storing it on the blacklist.
     */
    public function revokeToken(string $token, ?string $reason = null, ?callable $decoder = null): bool
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return false;
        }

        $payloadJson = $decoder ? $decoder($parts[1]) : base64_decode(strtr($parts[1], '-_', '+/'), true);
        if (! $payloadJson) {
            return false;
        }

        $payload = json_decode($payloadJson, true);
        if (! is_array($payload) || empty($payload['jti'])) {
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
        ?DateTimeInterface $expiresAt = null,
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

        if (! config('auth.jwt.blacklist_enabled', true)) {
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

        return $this->checkDatabaseAndWarmCache($jti, $cacheKey);
    }

    /**
     * Query DB for revocation status and warm L1 cache if found.
     */
    protected function checkDatabaseAndWarmCache(string $jti, string $cacheKey): bool
    {
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
}

