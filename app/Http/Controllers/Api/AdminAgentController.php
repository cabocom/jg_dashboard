<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\Agents\AgentRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AdminAgentController extends Controller
{
    protected AgentRegistrationService $registrationService;

    public function __construct(AgentRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    /**
     * List all registered agents.
     */
    public function index(): JsonResponse
    {
        $agents = Agent::orderByDesc('created_at')->get();

        return response()->json([
            'data' => $agents,
        ], 200);
    }

    /**
     * Register a new agent and issue a one-time bootstrap token.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:128',
            'version' => 'nullable|string|max:32',
            'capabilities' => 'nullable|array',
            'metadata' => 'nullable|array',
            'ttl_hours' => 'nullable|integer|min:1|max:168',
        ]);

        $result = $this->registrationService->createAgent(
            name: $validated['name'],
            version: $validated['version'] ?? null,
            capabilities: $validated['capabilities'] ?? null,
            metadata: $validated['metadata'] ?? null,
            ttlHours: (int) ($validated['ttl_hours'] ?? 24),
            adminUser: $request->user()
        );

        return response()->json([
            'agent_id' => $result['agent_id'],
            'name' => $result['name'],
            'status' => $result['status'],
            'bootstrap_token' => $result['bootstrap_token'],
            'expires_at' => $result['expires_at'],
            'ttl_hours' => $result['ttl_hours'],
            'created_at' => $result['agent']->created_at?->toISOString(),
        ], 201);
    }

    /**
     * Regenerate bootstrap token for a PENDING agent.
     */
    public function regenerateToken(Request $request, string $agentId): JsonResponse
    {
        $validated = $request->validate([
            'ttl_hours' => 'nullable|integer|min:1|max:168',
        ]);

        try {
            $result = $this->registrationService->regenerateBootstrapToken(
                agent: $agentId,
                ttlHours: (int) ($validated['ttl_hours'] ?? 24),
                adminUser: $request->user()
            );

            return response()->json([
                'agent_id' => $result['agent']->agent_id,
                'name' => $result['agent']->name,
                'status' => $result['agent']->status,
                'bootstrap_token' => $result['bootstrap_token'],
                'expires_at' => $result['expires_at'],
            ], 200);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => 'Bad Request',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Revoke all unconsumed bootstrap tokens for an agent.
     */
    public function revokeTokens(Request $request, string $agentId): JsonResponse
    {
        try {
            $revokedCount = $this->registrationService->revokeBootstrapTokens(
                agent: $agentId,
                adminUser: $request->user()
            );

            return response()->json([
                'status' => 'success',
                'message' => "Se revocaron {$revokedCount} token(s) de aprovisionamiento",
                'revoked_count' => $revokedCount,
            ], 200);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => 'Bad Request',
                'message' => $e->getMessage(),
            ], $e->getCode() === 404 ? 404 : 400);
        }
    }
}

