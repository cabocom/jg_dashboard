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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    /**
     * List registered agents with pagination, status filters, search and health metrics.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:ACTIVE,OFFLINE,PENDING,active,offline,pending',
            'search' => 'nullable|string|max:128',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Agent::query();

        if (!empty($validated['status'])) {
            $query->where('status', strtoupper($validated['status']));
        }

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('agent_id', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        // Aggregated summary metrics
        $summary = [
            'total' => Agent::count(),
            'active' => Agent::where('status', 'ACTIVE')->count(),
            'offline' => Agent::where('status', 'OFFLINE')->count(),
            'pending' => Agent::where('status', 'PENDING')->count(),
        ];

        // Format items with health details and hide secrets
        $items = collect($paginator->items())->map(function (Agent $agent) {
            return [
                'agent_id' => $agent->agent_id,
                'name' => $agent->name,
                'status' => $agent->status,
                'version' => $agent->version,
                'is_healthy' => $agent->isHealthy(90),
                'last_heartbeat_at' => $agent->last_heartbeat_at?->toIso8601String(),
                'capabilities' => $agent->capabilities_json,
                'metrics' => $agent->metrics_json,
                'created_at' => $agent->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $items,
            'summary' => $summary,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 200);
    }

    /**
     * Show detailed state and health of a specific agent.
     */
    public function show(string $agentId): JsonResponse
    {
        $agent = Agent::find($agentId);

        if (!$agent) {
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
    public function capabilities(string $agentId): JsonResponse
    {
        $agent = Agent::find($agentId);

        if (!$agent) {
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

        $client = \App\Services\Agents\AgentHttpClient::forAgent($agent);
        $response = $client->getCapabilities();

        if (!$response['success']) {
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

        // Sincronizar catálogo actualizado en la persistencia local del Dashboard
        if (!empty($capabilitiesData)) {
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
