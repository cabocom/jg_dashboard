<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Agents\AgentHeartbeatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AgentHeartbeatController extends Controller
{
    public function __construct(
        protected AgentHeartbeatService $heartbeatService
    ) {}

    /**
     * Receive and process periodic heartbeats from active agents.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        // 1. Extract Agent ID (Header X-Agent-ID or body agent_id)
        $agentId = $request->header('X-Agent-ID') ?: $request->input('agent_id');

        // 2. Extract Permanent Secret (Bearer token, X-Agent-Secret header, or body permanent_secret)
        $secret = $request->bearerToken()
            ?: ($request->header('X-Agent-Secret') ?: $request->input('permanent_secret'));

        if (empty($agentId) || empty($secret)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Identificador de agente o credencial permanente no provistos',
            ], 401);
        }

        // 3. Validate optional payload
        $validated = $request->validate([
            'status' => 'nullable|string|max:32',
            'metrics' => 'nullable|array',
            'active_executions' => 'nullable|integer|min:0',
        ]);

        $metrics = $validated['metrics'] ?? [];
        if (isset($validated['active_executions'])) {
            $metrics['active_executions'] = $validated['active_executions'];
        }

        try {
            $result = $this->heartbeatService->processHeartbeat(
                agentId: (string) $agentId,
                plainSecret: (string) $secret,
                status: $validated['status'] ?? null,
                metrics: !empty($metrics) ? $metrics : null
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Heartbeat registrado exitosamente',
                'agent_id' => $result['agent_id'],
                'name' => $result['name'],
                'agent_status' => $result['status'],
                'last_heartbeat_at' => $result['last_heartbeat_at'],
            ], 200);
        } catch (RuntimeException $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 500) ? $e->getCode() : 401;

            return response()->json([
                'error' => 'Unauthorized',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
