<?php

namespace App\Http\Middleware;

use App\Models\Agent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyHmacSignature
{
    /**
     * Maximum allowed clock skew in seconds (5 minutes) to protect against replay attacks.
     */
    protected const MAX_TIMESTAMP_SKEW_SECONDS = 300;

    /**
     * Handle an incoming request and authenticate the agent via HMAC-SHA256 canonical signature.
     *
     * Canonical string contract:
     * canonical_string = METHOD\nPATH\nTIMESTAMP\nSHA256(RAW_BODY)
     * signature = hash_hmac('sha256', canonical_string, secret)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');
        $agentId = $request->header('X-Agent-ID') ?: $request->input('agent_id');

        // 1. Validate mandatory security headers
        if (empty($signature) || empty($timestamp) || empty($agentId)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Cabeceras de autenticación HMAC requeridas (X-Signature, X-Timestamp, X-Agent-ID)',
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
        if (!$agent) {
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
        $normalizedPath = '/' . ltrim($request->getPathInfo(), '/');
        $rawBody = $request->getContent();
        $bodyHash = hash('sha256', $rawBody);

        $canonicalString = "{$normalizedMethod}\n{$normalizedPath}\n{$timestamp}\n{$bodyHash}";
        $expectedSignature = hash_hmac('sha256', $canonicalString, $secret);

        // 6. Constant-time signature verification
        if (!hash_equals(strtolower($expectedSignature), strtolower(trim((string) $signature)))) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Firma HMAC-SHA256 inválida.',
            ], 401);
        }

        // 7. Inject authenticated agent into request attributes
        $request->attributes->set('authenticated_agent', $agent);
        $request->attributes->set('agent_id', $agent->agent_id);

        return $next($request);
    }
}
