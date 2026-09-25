<?php

namespace App\Http\Middleware;

use App\Services\Auth\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Handle an incoming request verifying JWT bearer token.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Token de autenticación no provisto',
            ], 401);
        }

        $payload = $this->jwtService->verifyToken($token);

        if (!$payload) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Token de autenticación inválido o expirado',
            ], 401);
        }

        $user = $this->jwtService->getUserFromToken($token);

        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Usuario del token no encontrado o inactivo',
            ], 401);
        }

        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('jwt_payload', $payload);

        return $next($request);
    }
}
