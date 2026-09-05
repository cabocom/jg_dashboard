<?php

namespace App\Services\Agents;

use App\Models\Agent;
use RuntimeException;

class AgentHeartbeatService
{
    /**
     * Authenticate an active agent using its permanent secret and record its heartbeat.
     *
     * @param string $agentId
     * @param string $plainSecret
     * @param string|null $status
     * @param array<string, mixed>|null $metrics
     * @return array{
     *     agent: Agent,
     *     agent_id: string,
     *     name: string,
     *     status: string,
     *     last_heartbeat_at: string,
     *     metrics: ?array
     * }
     *
     * @throws RuntimeException
     */
    public function processHeartbeat(
        string $agentId,
        string $plainSecret,
        ?string $status = null,
        ?array $metrics = null
    ): array {
        $trimmedId = trim($agentId);
        $trimmedSecret = trim($plainSecret);

        if (empty($trimmedId) || empty($trimmedSecret)) {
            throw new RuntimeException('Credenciales de agente incompletas.', 401);
        }

        $agent = Agent::find($trimmedId);

        if (!$agent) {
            throw new RuntimeException('Agente no encontrado o no registrado.', 401);
        }

        if ($agent->status === 'PENDING') {
            throw new RuntimeException('El agente no ha completado el proceso de registro.', 401);
        }

        if (!$agent->verifyPermanentSecret($trimmedSecret)) {
            throw new RuntimeException('Credencial de agente inválida.', 401);
        }

        // Update heartbeat and metrics
        $agent->last_heartbeat_at = now();
        $agent->status = 'ACTIVE';

        if ($metrics !== null) {
            $agent->metrics_json = $metrics;
        }

        $agent->save();

        return [
            'agent' => $agent,
            'agent_id' => $agent->agent_id,
            'name' => $agent->name,
            'status' => $agent->status,
            'last_heartbeat_at' => $agent->last_heartbeat_at->toIso8601String(),
            'metrics' => $agent->metrics_json,
        ];
    }
}
