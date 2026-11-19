<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Actions\Agent;

use App\Models\Agent;
use App\Services\Agents\AgentHttpClient;
use Illuminate\Http\JsonResponse;

class FetchAgentCapabilitiesAction
{
    /**
     * Fetch the live tools, engines and capabilities catalog directly from the agent API.
     */
    public function execute(string $agentId): JsonResponse
    {
        $agent = Agent::find($agentId);

        if (! $agent) {
            return response()->json([
                'error' => 'Not Found',
                'message' => "El agente con ID '{$agentId}' no existe.",
            ], 404);
        }

        if ($agent->isPending()) {
            return response()->json([
                'error' => 'Agent Pending Registration',
                'message' => "El agente '{$agent->name}' aún se encuentra en estado PENDING y no ha completado su registro.",
            ], 400);
        }

        $client = AgentHttpClient::forAgent($agent);
        $response = $client->getCapabilities();

        if (! $response['success']) {
            $httpCode = match ($response['status_code']) {
                503 => 503,
                504 => 504,
                404 => 404,
                default => 502,
            };

            return response()->json([
                'error' => 'Agent Gateway Error',
                'message' => $response['error'] ?? 'Error al consultar capacidades en jg_agente',
                'agent_id' => $agentId,
                'upstream_status' => $response['status_code'],
            ], $httpCode);
        }

        $capabilitiesData = $response['data'] ?? [];

        if (! empty($capabilitiesData)) {
            $agent->update([
                'capabilities_json' => $capabilitiesData,
            ]);
        }

        return response()->json([
            'agent_id' => $agent->agent_id,
            'name' => $agent->name,
            'status' => $agent->status,
            'version' => $agent->version,
            'capabilities' => $capabilitiesData,
            'cached_at' => now()->toIso8601String(),
            'latency_ms' => $response['latency_ms'],
        ], 200);
    }
}
