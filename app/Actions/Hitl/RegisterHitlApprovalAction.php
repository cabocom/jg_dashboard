<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Actions\Hitl;

use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Agents\AgentHttpClient;
use Illuminate\Support\Facades\Log;

class RegisterHitlApprovalAction
{
    /**
     * Register or update a pending Human-In-The-Loop approval request.
     *
     * @param array{
     *     thread_id: string,
     *     agent_id?: ?string,
     *     task_id?: ?string,
     *     risk_level?: ?string,
     *     proposed_plan?: ?array,
     *     plan?: ?array,
     *     compensation_commands?: ?array,
     *     tools?: ?array
     * } $data
     */
    public function execute(
        array $data,
        ?User $initiator = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): ApprovalRequest {
        $threadId = (string) ($data['thread_id'] ?? '');
        if (empty($threadId)) {
            throw new \InvalidArgumentException("El campo 'thread_id' es obligatorio para registrar una solicitud HITL.");
        }

        $agentId = $data['agent_id'] ?? null;
        $taskId = $data['task_id'] ?? ($data['execution_id'] ?? null);
        $riskLevel = strtoupper((string) ($data['risk_level'] ?? 'HIGH'));

        // Normalizar plan y herramientas involucradas
        $plan = (array) ($data['proposed_plan'] ?? ($data['plan'] ?? []));
        $tools = (array) ($data['tools'] ?? ($plan['tools'] ?? ($plan['tools_involved'] ?? [])));
        $compensation = (array) ($data['compensation_commands'] ?? []);

        if (! empty($tools) && ! isset($plan['tools_involved'])) {
            $plan['tools_involved'] = $tools;
        }

        if (! empty($data['prompt']) && ! isset($plan['prompt'])) {
            $plan['prompt'] = $data['prompt'];
        }

        if (! empty($data['cognitive_steps']) && ! isset($plan['cognitive_steps'])) {
            $plan['cognitive_steps'] = $data['cognitive_steps'];
        }

        if (empty($plan['prompt']) || empty($plan['cognitive_steps'])) {
            $plan = $this->hydratePlanFromAgent($plan, $threadId, $agentId);
        }

        // Evitar duplicidades: si ya existe una solicitud para este hilo, reutilizarla o no recrear si ya está en proceso/resuelta
        /** @var ApprovalRequest|null $approval */
        $approval = ApprovalRequest::where('thread_id', $threadId)
            ->orderByDesc('id')
            ->first();

        if ($approval && in_array($approval->status, ['PROCESSING', 'APPROVED', 'REJECTED', 'CANCELLED'], true)) {
            Log::info("[RegisterHitlApprovalAction] Omitiendo recreación de solicitud HITL para thread '{$threadId}' (ID: {$approval->id}, Estado actual: {$approval->status})");

            return $approval;
        }

        $attributes = [
            'agent_id' => $agentId,
            'task_id' => $taskId,
            'status' => 'PENDING',
            'risk_level' => $riskLevel,
            'proposed_plan_json' => $plan,
            'compensation_commands_json' => $compensation,
        ];

        if ($approval) {
            $approval->update($attributes);
            Log::info("[RegisterHitlApprovalAction] Solicitud HITL existente actualizada para thread '{$threadId}' (ID: {$approval->id})");
        } else {
            $attributes['thread_id'] = $threadId;
            $approval = ApprovalRequest::create($attributes);
            Log::info("[RegisterHitlApprovalAction] Nueva solicitud HITL registrada para thread '{$threadId}' (ID: {$approval->id}, Riesgo: {$riskLevel})");
        }

        // Registrar evento de auditoría en el Dashboard
        AuditEvent::record(
            eventType: 'hitl.requested',
            actionSummary: "Solicitud HITL registrada para hilo {$threadId} (Riesgo: {$riskLevel})",
            payload: [
                'approval_id' => $approval->id,
                'thread_id' => $threadId,
                'agent_id' => $agentId,
                'task_id' => $taskId,
                'risk_level' => $riskLevel,
                'tools_count' => count($tools),
                'has_compensation' => ! empty($compensation),
            ],
            user: $initiator,
            agent: $agentId,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        return $approval;
    }

    /**
     * Sincroniza pasivamente prompt y pasos del agente si no fueron provistos en el payload inicial.
     */
    protected function hydratePlanFromAgent(array $plan, string $threadId, ?string $agentId): array
    {
        try {
            $agent = $agentId ? Agent::where('agent_id', $agentId)->first() : Agent::active()->first();
            if (! $agent) {
                return $plan;
            }

            $client = AgentHttpClient::forAgent($agent);
            $statusRes = $client->getExecutionStatus($threadId);
            if ($statusRes['success'] && ! empty($statusRes['data'])) {
                $agentData = $statusRes['data'];
                $plan['prompt'] = $plan['prompt'] ?? ($agentData['prompt'] ?? ($agentData['goal'] ?? null));
                $plan['cognitive_steps'] = $plan['cognitive_steps'] ?? ($agentData['cognitive_steps'] ?? ($agentData['steps'] ?? []));
                $fetchedPlan = $agentData['plan'] ?? [];
                if (empty($plan['steps']) && ! empty($fetchedPlan['steps'])) {
                    $plan['steps'] = $fetchedPlan['steps'];
                }
                if (empty($plan['tools_involved']) && ! empty($fetchedPlan['steps'])) {
                    $planTools = array_filter(array_map(fn ($s) => $s['tool_name'] ?? null, $fetchedPlan['steps']));
                    if (! empty($planTools)) {
                        $plan['tools_involved'] = array_values($planTools);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[RegisterHitlApprovalAction] Sincronización pasiva de plan omitida para thread '{$threadId}': ".$e->getMessage());
        }

        return $plan;
    }
}

