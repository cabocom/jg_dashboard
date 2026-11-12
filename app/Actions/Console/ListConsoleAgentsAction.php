<?php

declare(strict_types=1);

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Actions\Console;

use App\Models\Agent;
use App\Models\AgentTask;
use Illuminate\Http\JsonResponse;

class ListConsoleAgentsAction
{
    public function execute(): JsonResponse
    {
        $agents = Agent::orderBy('created_at', 'desc')->get();
        $taskCounts = AgentTask::all()->groupBy('agent_id')->map->count();

        $items = $agents->map(function (Agent $agent) use ($taskCounts) {
            $metrics = $agent->metrics_json ?? [];
            $capabilities = $agent->capabilities_json ?? [];
            $isHealthy = $agent->status === 'ACTIVE' && $agent->isHealthy(90);
            $effectiveStatus = ($agent->status === 'ACTIVE' && !$isHealthy) ? 'OFFLINE' : $agent->status;

            return [
                'agent_id' => $agent->agent_id,
                'name' => $agent->name,
                'status' => $effectiveStatus,
                'version' => $agent->version,
                'type' => $agent->type,
                'is_healthy' => $isHealthy,
                'executions_count' => $taskCounts->get($agent->agent_id, 0),
                'last_heartbeat_at' => $agent->last_heartbeat_at?->toIso8601String(),
                'capabilities' => $capabilities,
                'capabilities_json' => $capabilities,
                'metrics' => $metrics,
                'metrics_json' => $metrics,
                'metadata' => $agent->metadata_json ?? [],
                'created_at' => $agent->created_at?->toIso8601String(),
            ];
        });

        $total = $items->count();
        $active = $items->where('status', 'ACTIVE')->count();
        $offline = $items->where('status', 'OFFLINE')->count();

        return response()->json([
            'data' => $items,
            'summary' => [
                'total' => $total,
                'active' => $active,
                'offline' => $offline,
            ],
        ]);
    }
}

