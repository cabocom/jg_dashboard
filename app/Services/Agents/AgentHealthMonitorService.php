<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AgentHealthMonitorService
{
    /**
     * Identify all active agents whose last heartbeat exceeds the timeout and mark them as OFFLINE.
     *
     * @param int $timeoutSeconds Number of seconds without heartbeat before declaring OFFLINE (default 90s).
     * @return Collection<int, Agent> Collection of agents transitioned to OFFLINE.
     */
    public function checkAndMarkOfflineAgents(int $timeoutSeconds = 90): Collection
    {
        $timedOutAgents = Agent::timedOut($timeoutSeconds)->get();

        if ($timedOutAgents->isEmpty()) {
            return new Collection();
        }

        return DB::transaction(function () use ($timedOutAgents, $timeoutSeconds) {
            foreach ($timedOutAgents as $agent) {
                $previousHeartbeat = $agent->last_heartbeat_at?->toIso8601String() ?? 'N/A';

                $agent->markAsOffline();

                AuditEvent::record(
                    eventType: 'AGENT_OFFLINE',
                    actionSummary: "Agente [{$agent->name}] marcado automáticamente como OFFLINE por inactividad (sin heartbeat >{$timeoutSeconds}s)",
                    payload: [
                        'agent_id' => $agent->agent_id,
                        'name' => $agent->name,
                        'previous_status' => 'ACTIVE',
                        'last_heartbeat_at' => $previousHeartbeat,
                        'timeout_seconds' => $timeoutSeconds,
                    ],
                    agent: $agent
                );
            }

            return $timedOutAgents;
        });
    }
}
