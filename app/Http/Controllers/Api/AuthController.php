<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

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
}
