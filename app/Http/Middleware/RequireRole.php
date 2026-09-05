<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Handle an incoming request requiring one of the specified roles.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Autenticación requerida',
            ], 401);
        }

        if (!$user->hasRole($roles)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Acceso denegado: rol insuficiente',
            ], 403);
        }

        return $next($request);
    }
}
