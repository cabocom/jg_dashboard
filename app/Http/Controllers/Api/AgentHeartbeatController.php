<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\Agents\AgentHeartbeatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AgentHeartbeatController extends Controller
{
    protected AgentHeartbeatService $heartbeatService;

    public function __construct(AgentHeartbeatService $heartbeatService)
    {
        $this->heartbeatService = $heartbeatService;
    }

    /**
     * Receive and process periodic heartbeats from active agents.
     * Authenticated via VerifyHmacSignature middleware ('agent.hmac').
     */
    public function heartbeat(Request $request): JsonResponse
    {
        /** @var Agent|null $agent */
        $agent = $request->attributes->get('authenticated_agent');

        if (!$agent) {
            $agentId = $request->header('X-Agent-ID') ?: $request->input('agent_id');
            if ($agentId) {
                $agent = Agent::find((string) $agentId);
            }
        }

        if (!$agent) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Agente no autenticado o no encontrado',
            ], 401);
        }

        // Validate optional payload
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
            $result = $this->heartbeatService->recordHeartbeat(
                agent: $agent,
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
