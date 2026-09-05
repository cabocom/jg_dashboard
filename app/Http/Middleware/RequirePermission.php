<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * Handle an incoming request requiring a specific permission.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Autenticación requerida',
            ], 401);
        }

        if (!$user->hasPermission($permission)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Acceso denegado: permiso insuficiente',
            ], 403);
        }

        return $next($request);
    }
}
