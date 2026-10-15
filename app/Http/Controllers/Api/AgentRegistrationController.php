<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Agents\AgentRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AgentRegistrationController extends Controller
{
    protected AgentRegistrationService $registrationService;

    public function __construct(AgentRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    /**
     * Handle the registration of an agent presenting a valid bootstrap token.
     * Tokens can be provided via Authorization Bearer header or request body.
     */
    public function register(Request $request): JsonResponse
    {
        // 1. Extract bootstrap token from Bearer header or request payload
        $plainToken = $request->bearerToken() ?: $request->input('bootstrap_token');

        if (empty($plainToken) || !is_string($plainToken)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Bootstrap token no provisto',
            ], 401);
        }

        // 2. Validate optional payload fields
        $validated = $request->validate([
            'version' => 'nullable|string|max:32',
            'capabilities' => 'nullable|array',
            'metadata' => 'nullable|array',
            'agent_name' => 'nullable|string|max:128',
        ]);

        try {
            $result = $this->registrationService->consumeBootstrapTokenAndActivate(
                plainToken: trim($plainToken),
                version: $validated['version'] ?? null,
                capabilities: $validated['capabilities'] ?? null,
                metadata: $validated['metadata'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Agente aprovisionado y activado exitosamente',
                'agent_id' => $result['agent_id'],
                'name' => $result['name'],
                'status' => $result['status'],
                'permanent_secret' => $result['permanent_secret'],
                'registered_at' => $result['registered_at'],
            ], 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? (int) $code : 400;

            $errorName = match ($statusCode) {
                401 => 'Unauthorized',
                404 => 'Not Found',
                409 => 'Conflict',
                410 => 'Gone',
                default => 'Bad Request',
            };

            return response()->json([
                'error' => $errorName,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
