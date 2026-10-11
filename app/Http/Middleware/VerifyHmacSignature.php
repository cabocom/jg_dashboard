<?php

namespace App\Http\Middleware;

use App\Models\Agent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyHmacSignature
{
    /**
     * Maximum allowed clock skew in seconds (5 minutes) to protect against replay attacks.
     */
    protected const MAX_TIMESTAMP_SKEW_SECONDS = 300;

    /**
     * Nonce retention: a timestamp stays valid for +/- skew, so a captured request can be replayed
     * for up to 2x skew (future-dated timestamp). The nonce must outlive that whole window.
     */
    protected const NONCE_TTL_SECONDS = self::MAX_TIMESTAMP_SKEW_SECONDS * 2;

    /**
     * Handle an incoming request and authenticate the agent via HMAC-SHA256 canonical signature.
     *
     * Canonical string contract:
     * canonical_string = METHOD\nPATH\nTIMESTAMP\nSHA256(RAW_BODY)[\nNONCE]
     * signature = hash_hmac('sha256', canonical_string, secret)
     *
     * The optional X-Nonce is part of the signed material (it cannot be stripped or swapped) and
     * is single-use: replays inside the time window are rejected via the shared cache.
     * Set HMAC_REQUIRE_NONCE=true (config security.hmac_require_nonce) to make it mandatory.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');
        $agentId = $request->header('X-Agent-ID') ?: $request->input('agent_id');
        $nonce = trim((string) $request->header('X-Nonce', ''));

        // 1. Validate mandatory security headers
        if (empty($signature) || empty($timestamp) || empty($agentId)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Cabeceras de autenticación HMAC requeridas (X-Signature, X-Timestamp, X-Agent-ID)',
            ], 401);
        }

        if ($nonce === '' && (bool) config('security.hmac_require_nonce', false)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Cabecera X-Nonce requerida.',
            ], 401);
        }

        if ($nonce !== '' && ! preg_match('/^[A-Za-z0-9._-]{8,128}$/', $nonce)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Formato de X-Nonce inválido.',
            ], 401);
        }

        // 2. Anti-replay check: ensure timestamp is within tolerance window
        $currentEpoch = time();
        $ts = (int) $timestamp;
        if (abs($currentEpoch - $ts) > self::MAX_TIMESTAMP_SKEW_SECONDS) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Timestamp fuera de la ventana de tolerancia temporal (300s)',
            ], 401);
        }

        // 3. Look up registered Agent
        $agent = Agent::find(trim((string) $agentId));
        if (! $agent) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Agente no encontrado o no registrado.',
            ], 401);
        }

        if ($agent->status === 'PENDING') {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'El agente no ha completado el proceso de registro.',
            ], 401);
        }

        // 4. Resolve agent cryptographic secret
        $secret = $agent->resolveHmacSecret();
        if (empty($secret)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Secreto de agente no disponible para verificación HMAC.',
            ], 401);
        }

        // 5. Reconstruct canonical string and compute expected signature
        $normalizedMethod = strtoupper($request->getMethod());
        $normalizedPath = '/'.ltrim($request->getPathInfo(), '/');
        $rawBody = $request->getContent();
        $bodyHash = hash('sha256', $rawBody);

        $canonicalString = "{$normalizedMethod}\n{$normalizedPath}\n{$timestamp}\n{$bodyHash}";
        if ($nonce !== '') {
            $canonicalString .= "\n{$nonce}";
        }
        $expectedSignature = hash_hmac('sha256', $canonicalString, $secret);

        // 6. Constant-time signature verification
        if (! hash_equals(strtolower($expectedSignature), strtolower(trim((string) $signature)))) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Firma HMAC-SHA256 inválida.',
            ], 401);
        }

        // 6b. Anti-replay: the nonce is single-use. Registered only AFTER the signature is valid so that
        // unauthenticated callers cannot burn legitimate nonces. Cache::add is atomic (SET NX).
        if ($nonce !== '') {
            $nonceKey = 'hmac_nonce:'.trim((string) $agentId).':'.$nonce;
            if (! Cache::add($nonceKey, true, self::NONCE_TTL_SECONDS)) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Nonce replayed',
                ], 401);
            }
        }

        // 7. Inject authenticated agent into request attributes
        $request->attributes->set('authenticated_agent', $agent);
        $request->attributes->set('agent_id', $agent->agent_id);

        return $next($request);
    }
}
