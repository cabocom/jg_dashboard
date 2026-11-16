<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * Maneja la autenticación por token de aplicación para clientes externos y servidores MCP.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $bearer = $request->bearerToken();

        if (empty($bearer)) {
            return response()->json([
                'error' => 'Unauthorized: Missing or empty bearer token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $hash = hash('sha256', $bearer);
        $apiToken = ApiToken::where('token_hash', $hash)->first();

        if (! $apiToken || ! $apiToken->isValid()) {
            return response()->json([
                'error' => 'Unauthorized: Invalid, expired or revoked API token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($requiredScope !== null && ! $apiToken->hasScope($requiredScope)) {
            return response()->json([
                'error' => "Forbidden: Token lacks required scope '{$requiredScope}'",
            ], Response::HTTP_FORBIDDEN);
        }

        $apiToken->forceFill(['last_used_at' => now()])->saveQuietly();

        if ($apiToken->user) {
            auth()->setUser($apiToken->user);
        }

        $request->attributes->set('api_token', $apiToken);

        return $next($request);
    }
}
