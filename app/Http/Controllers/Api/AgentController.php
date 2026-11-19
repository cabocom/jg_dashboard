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

use App\Actions\Agent\FetchAgentCapabilitiesAction;
use App\Actions\Agent\ListAgentsApiAction;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    /**
     * List registered agents with pagination, status filters, search and health metrics.
     */
    public function index(Request $request, ListAgentsApiAction $action): JsonResponse
    {
        return $action->execute($request);
    }

    /**
     * Show detailed state and health of a specific agent.
     */
    public function show(string $agentId): JsonResponse
    {
        $agent = Agent::find($agentId);

        if (! $agent) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Agente no encontrado.',
            ], 404);
        }

        return response()->json([
            'agent_id' => $agent->agent_id,
            'name' => $agent->name,
            'status' => $agent->status,
            'version' => $agent->version,
            'is_healthy' => $agent->isHealthy(90),
            'last_heartbeat_at' => $agent->last_heartbeat_at?->toIso8601String(),
            'capabilities' => $agent->capabilities_json,
            'metrics' => $agent->metrics_json,
            'metadata' => $agent->metadata_json,
            'created_at' => $agent->created_at?->toIso8601String(),
            'updated_at' => $agent->updated_at?->toIso8601String(),
        ], 200);
    }

    /**
     * Fetch the live tools, engines and capabilities catalog directly from the agent API.
     */
    public function capabilities(string $agentId, FetchAgentCapabilitiesAction $action): JsonResponse
    {
        return $action->execute($agentId);
    }
}
