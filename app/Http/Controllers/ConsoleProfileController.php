<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Http\Controllers;

use App\Actions\Console\UpdateConsoleProfileAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsoleProfileController extends Controller
{
    /**
     * Get current authenticated user profile for the web console.
     */
    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated', 'message' => 'No autenticado.'], 401);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
        ], 200);
    }

    /**
     * Update current authenticated user profile and password.
     */
    public function updateProfile(Request $request, UpdateConsoleProfileAction $action): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated', 'message' => 'No autenticado.'], 401);
        }

        return $action->execute($request, $user);
    }
}
