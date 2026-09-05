<?php

namespace App\Services\Hitl;

use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class HitlApprovalService
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
     * @param User|null $initiator
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return ApprovalRequest
     */
    public function registerPendingApproval(
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

        if (!empty($tools) && !isset($plan['tools_involved'])) {
            $plan['tools_involved'] = $tools;
        }

        // Evitar duplicidades: si ya existe una solicitud PENDING para este hilo, la actualizamos
        /** @var ApprovalRequest|null $approval */
        $approval = ApprovalRequest::where('thread_id', $threadId)
            ->where('status', 'PENDING')
            ->first();

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
            Log::info("[HitlApprovalService] Solicitud HITL existente actualizada para thread '{$threadId}' (ID: {$approval->id})");
        } else {
            $attributes['thread_id'] = $threadId;
            $approval = ApprovalRequest::create($attributes);
            Log::info("[HitlApprovalService] Nueva solicitud HITL registrada para thread '{$threadId}' (ID: {$approval->id}, Riesgo: {$riskLevel})");
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
                'has_compensation' => !empty($compensation),
            ],
            user: $initiator,
            agent: $agentId,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        return $approval;
    }

    /**
     * Retrieve paginated pending approval requests.
     */
    public function getPendingApprovals(
        ?string $agentId = null,
        ?string $riskLevel = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = ApprovalRequest::with(['agent'])
            ->pending()
            ->orderByDesc('created_at');

        if (!empty($agentId)) {
            $query->where('agent_id', $agentId);
        }

        if (!empty($riskLevel)) {
            $query->where('risk_level', strtoupper($riskLevel));
        }

        return $query->paginate($perPage);
    }

    /**
     * Extract a human-readable action summary from the proposed plan.
     */
    public function extractActionSummary(ApprovalRequest $approval): string
    {
        $plan = $approval->proposed_plan_json ?? [];

        if (!empty($plan['summary']) && is_string($plan['summary'])) {
            return $plan['summary'];
        }

        if (!empty($plan['action']) && is_string($plan['action'])) {
            return $plan['action'];
        }

        if (!empty($plan['description']) && is_string($plan['description'])) {
            return $plan['description'];
        }

        if (!empty($plan['command']) && is_string($plan['command'])) {
            return "Ejecución de comando: {$plan['command']}";
        }

        if (!empty($plan['step']) && is_string($plan['step'])) {
            return "Paso del plan: {$plan['step']}";
        }

        $tools = $plan['tools_involved'] ?? ($plan['tools'] ?? []);
        if (!empty($tools) && is_array($tools)) {
            return "Ejecución de herramientas: " . implode(', ', $tools);
        }

        return "Plan de ejecución pendiente de revisión humana";
    }

    /**
     * Format an approval request into a standardized API payload.
     *
     * @return array<string, mixed>
     */
    public function formatApprovalItem(ApprovalRequest $approval): array
    {
        $plan = $approval->proposed_plan_json ?? [];
        $tools = $plan['tools_involved'] ?? ($plan['tools'] ?? []);
        $now = now();

        return [
            'id' => $approval->id,
            'thread_id' => $approval->thread_id,
            'task_id' => $approval->task_id,
            'agent_id' => $approval->agent_id,
            'agent_name' => $approval->agent?->name ?? 'Agente Desconocido',
            'status' => $approval->status,
            'risk_level' => $approval->risk_level,
            'action_summary' => $this->extractActionSummary($approval),
            'tools_involved' => is_array($tools) ? array_values($tools) : [],
            'proposed_plan' => $plan,
            'compensation_commands' => $approval->compensation_commands_json ?? [],
            'created_at' => $approval->created_at?->toIso8601String(),
            'elapsed_time' => $approval->created_at?->diffForHumans(),
            'elapsed_seconds' => $approval->created_at ? max(0, (int) $approval->created_at->diffInSeconds($now)) : 0,
        ];
    }

    /**
     * Find an approval request by its primary key.
     */
    public function findApproval(int|string $id): ?ApprovalRequest
    {
        return ApprovalRequest::with(['agent', 'resolver'])->find($id);
    }

    /**
     * Find an active pending approval request by thread ID.
     */
    public function findPendingByThread(string $threadId): ?ApprovalRequest
    {
        return ApprovalRequest::with(['agent', 'resolver'])
            ->where('thread_id', $threadId)
            ->pending()
            ->first();
    }
}
