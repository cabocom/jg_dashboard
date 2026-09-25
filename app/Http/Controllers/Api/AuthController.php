<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle user login and issue JWT token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $result = $this->authService->attempt($validated['email'], $validated['password']);

        if (!$result) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Credenciales inválidas',
            ], 401);
        }

        return response()->json($result, 200);
    }

    /**
     * Get authenticated user profile with roles and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'No autenticado',
            ], 401);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'permissions' => $user->getPermissions(),
            'created_at' => $user->created_at?->toISOString(),
        ], 200);
    }

    /**
     * Invalidate/logout current session.
     */
    public function logout(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Sesión cerrada exitosamente',
        ], 200);
    }
}
