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
    public function getPendingApprovals(?string $agentId = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = ApprovalRequest::with(['agent'])
            ->pending()
            ->orderByDesc('created_at');

        if (!empty($agentId)) {
            $query->where('agent_id', $agentId);
        }

        return $query->paginate($perPage);
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
