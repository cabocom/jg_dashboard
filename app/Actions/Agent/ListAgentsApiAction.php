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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListAgentsApiAction
{
    /**
     * List registered agents with pagination, status filters, search and health metrics.
     */
    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:ACTIVE,OFFLINE,PENDING,active,offline,pending',
            'search' => 'nullable|string|max:128',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Agent::query();

        if (! empty($validated['status'])) {
            $query->where('status', strtoupper($validated['status']));
        }

        if (! empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('agent_id', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 50);
        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        $summary = [
            'total' => Agent::count(),
            'active' => Agent::where('status', 'ACTIVE')->count(),
            'offline' => Agent::where('status', 'OFFLINE')->count(),
            'pending' => Agent::where('status', 'PENDING')->count(),
        ];

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
}
