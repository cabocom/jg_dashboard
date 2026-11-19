<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Services\Hitl;

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Agents\AgentHttpClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResolveHitlRequestService
{
    public function __construct(
        protected HitlApprovalFormatter $formatter = new HitlApprovalFormatter,
        protected HitlTaskSynchronizer $taskSynchronizer = new HitlTaskSynchronizer
    ) {}

    /**
     * Resolve a pending approval request (approve or reject) in a concurrency-safe transaction.
     *
     * @return array{success: bool, status_code: int, data?: array, error?: string}
     */
    public function resolve(
        int|string $approvalId,
        User $resolver,
        string $actionType,
        ?string $justification = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $lockResult = $this->lockAndPrepare($approvalId);
        if ($lockResult['status_code'] !== 200) {
            return ['success' => false, 'status_code' => $lockResult['status_code'], 'error' => $lockResult['error']];
        }

        /** @var ApprovalRequest $approval */
        $approval = $lockResult['record'];
        $agent = $approval->agent ?? Agent::where('agent_id', $approval->agent_id)->first() ?? Agent::active()->first();

        try {
            $agentResponse = $this->dispatchAgentCall($approval, $agent, $resolver, $actionType, $justification);
        } catch (\Throwable $e) {
            $this->revertToPending($approval);
            Log::error("[ResolveHitlRequestService] Excepción de red al delegar '{$actionType}' al agente '{$agent->agent_id}' para thread '{$approval->thread_id}'", ['error' => $e->getMessage()]);

            return ['success' => false, 'status_code' => 500, 'error' => "Fallo de conexión al comunicar la resolución ({$actionType}) con el agente: ".$e->getMessage()];
        }

        if (! $agentResponse['success']) {
            return $this->handleAgentFailure($approval, $agent, $resolver, $actionType, $justification, $agentResponse, $ipAddress, $userAgent);
        }

        return $this->finalizeSuccess($approval, $agent, $resolver, $actionType, $justification, $agentResponse, $ipAddress, $userAgent);
    }

    /**
     * Pessimistic lock and initial state transition to PROCESSING.
     *
     * @return array{status_code: int, error?: string, record?: ApprovalRequest}
     */
    protected function lockAndPrepare(int|string $approvalId): array
    {
        return DB::transaction(function () use ($approvalId) {
            /** @var ApprovalRequest|null $record */
            $record = ApprovalRequest::with(['agent', 'resolver'])->where('id', $approvalId)->lockForUpdate()->first();

            if (! $record) {
                return ['status_code' => 404, 'error' => "Solicitud de aprobación con ID '{$approvalId}' no encontrada."];
            }
            if ($record->status === 'PROCESSING') {
                return ['status_code' => 409, 'error' => "La solicitud '{$approvalId}' ya se encuentra en procesamiento."];
            }
            if (! $record->isPending()) {
                return ['status_code' => 409, 'error' => "La solicitud '{$approvalId}' ya fue resuelta previamente con estado '{$record->status}'."];
            }

            $agent = $record->agent;
            if (! $agent || $agent->status === 'PENDING') {
                $msg = ! $agent ? "asociado a la solicitud '{$approvalId}' no encontrado." : "aún está en estado PENDING.";

                return ['status_code' => 400, 'error' => "El agente registrado {$msg}"];
            }

            $record->update(['status' => 'PROCESSING', 'processing_started_at' => now()]);
            AgentTask::where(function ($q) use ($record) {
                if (! empty($record->task_id)) {
                    $q->where('task_id', $record->task_id);
                }
                if (! empty($record->thread_id)) {
                    $q->orWhere('thread_id', $record->thread_id);
                }
            })->where('status', 'WAITING_APPROVAL')->update(['status' => 'PROCESSING']);

            return ['status_code' => 200, 'record' => $record];
        });
    }

    /**
     * Dispatch approve or reject HTTP call to upstream agent.
     */
    protected function dispatchAgentCall(ApprovalRequest $approval, Agent $agent, User $resolver, string $actionType, ?string $justification): array
    {
        $client = $agent ? AgentHttpClient::forAgent($agent) : (new AgentHttpClient)->withAgentId($approval->agent_id);
        $isApprove = ($actionType === 'approve');
        $payload = [
            'thread_id' => $approval->thread_id,
            'approved' => $isApprove,
            'notes' => $justification ?: ($isApprove ? 'Aprobado por operador' : 'Rechazado por operador'),
            'justification' => $justification,
            'approver' => $resolver->name ?: $resolver->email,
            'user' => ['id' => $resolver->id, 'name' => $resolver->name, 'email' => $resolver->email, 'role' => $resolver->role],
        ];

        return $isApprove ? $client->approveExecution($approval->thread_id, $payload) : $client->rejectExecution($approval->thread_id, $payload);
    }

    /**
     * Handle upstream agent failure response (including 404/409 reconciliation).
     */
    protected function handleAgentFailure(
        ApprovalRequest $approval,
        Agent $agent,
        User $resolver,
        string $actionType,
        ?string $justification,
        array $agentResponse,
        ?string $ipAddress,
        ?string $userAgent
    ): array {
        if (in_array($agentResponse['status_code'], [404, 409], true)) {
            return $this->reconcileTerminalResponse($approval, $agent, $resolver, $actionType, $justification, $agentResponse, $ipAddress, $userAgent);
        }

        $this->revertToPending($approval);
        Log::error("[ResolveHitlRequestService] Error al delegar '{$actionType}' al agente '{$agent->agent_id}' para thread '{$approval->thread_id}'", [
            'status_code' => $agentResponse['status_code'],
            'error' => $agentResponse['error'],
        ]);

        return [
            'success' => false,
            'status_code' => $agentResponse['status_code'],
            'error' => "Error al comunicar la resolución ({$actionType}) con el agente: ".($agentResponse['error'] ?? 'Error desconocido'),
        ];
    }

    /**
     * Reconcile local state when upstream thread already terminated (404/409).
     */
    protected function reconcileTerminalResponse(
        ApprovalRequest $approval,
        Agent $agent,
        User $resolver,
        string $actionType,
        ?string $justification,
        array $agentResponse,
        ?string $ipAddress,
        ?string $userAgent
    ): array {
        $isApprove = ($actionType === 'approve');
        $reason = $agentResponse['error'] ?? 'El hilo en el agente ya concluyó o no requiere resolución activa';
        Log::info("[ResolveHitlRequestService] Reconciliando resolución ({$actionType}) {$approval->id} (hilo {$approval->thread_id}): {$reason}");

        $justSuffix = ($justification ? $justification.' ' : '')."[Reconciliado: {$reason}]";
        if ($isApprove) {
            $approval->approve($resolver, $justSuffix);
            $this->taskSynchronizer->syncAssociatedTask($approval, 'PROCESSING');
        } else {
            $approval->reject($resolver, $justSuffix);
            $this->taskSynchronizer->syncAssociatedTask($approval, 'CANCELLED');
        }

        $freshApproval = $approval->fresh(['agent', 'resolver']);
        $this->recordAuditEvent($approval, $agent, $resolver, $actionType, true, $agentResponse, $justification, $ipAddress, $userAgent);

        return [
            'success' => true,
            'status_code' => 200,
            'data' => [
                'approval' => $this->formatter->formatApprovalItem($freshApproval),
                'agent_response' => $agentResponse['data'] ?? null,
                'reconciled' => true,
                'message' => "Solicitud reconciliada y {$actionType}da (el hilo en el agente ya había concluido).",
            ],
        ];
    }

    /**
     * Finalize successful resolution on approval and sync task/audit.
     */
    protected function finalizeSuccess(
        ApprovalRequest $approval,
        Agent $agent,
        User $resolver,
        string $actionType,
        ?string $justification,
        array $agentResponse,
        ?string $ipAddress,
        ?string $userAgent
    ): array {
        $isApprove = ($actionType === 'approve');
        if ($isApprove) {
            $approval->approve($resolver, $justification);
        } else {
            $approval->reject($resolver, $justification);
        }

        $freshApproval = $approval->fresh(['agent', 'resolver']);
        $respData = $agentResponse['data'] ?? [];

        if ($isApprove) {
            $respStatus = strtolower($respData['status'] ?? '');
            $finalStatus = match ($respStatus) {
                'completed', 'success', 'resolved' => 'COMPLETED',
                'failed', 'error' => 'FAILED',
                'cancelled' => 'CANCELLED',
                default => 'PROCESSING',
            };
            $this->taskSynchronizer->syncAssociatedTask($approval, $finalStatus, null, $respData);
        } else {
            $this->taskSynchronizer->syncAssociatedTask($approval, 'CANCELLED', 'Rechazado por operador: '.$justification);
        }

        $this->recordAuditEvent($approval, $agent, $resolver, $actionType, false, $agentResponse, $justification, $ipAddress, $userAgent);
        Log::info("[ResolveHitlRequestService] Resolución ({$actionType}) concedida exitosamente para solicitud {$approval->id} (hilo {$approval->thread_id}) por usuario {$resolver->email}");

        return [
            'success' => true,
            'status_code' => 200,
            'data' => [
                'approval' => $this->formatter->formatApprovalItem($freshApproval),
                'agent_response' => $agentResponse['data'] ?? null,
            ],
        ];
    }

    /**
     * Record structured AuditEvent for HITL resolution.
     */
    protected function recordAuditEvent(ApprovalRequest $approval, Agent $agent, User $resolver, string $actionType, bool $reconciled, array $agentResponse, ?string $justification, ?string $ipAddress, ?string $userAgent): void
    {
        $isApprove = ($actionType === 'approve');
        $eventType = $isApprove ? 'hitl.approved' : 'hitl.rejected';
        $summary = $isApprove ? ($reconciled ? 'Aprobación HITL reconciliada' : 'Aprobación HITL concedida') : ($reconciled ? 'Rechazo HITL reconciliado' : 'Rechazo HITL ejecutado');

        AuditEvent::record(
            eventType: $eventType,
            actionSummary: "{$summary} para hilo {$approval->thread_id} por {$resolver->email}",
            payload: [
                'approval_id' => $approval->id,
                'thread_id' => $approval->thread_id,
                'agent_id' => $agent->agent_id,
                'resolver_id' => $resolver->id,
                'resolver_email' => $resolver->email,
                'justification' => $justification,
                'risk_level' => $approval->risk_level,
                'reconciled' => $reconciled,
                'agent_response' => $agentResponse['data'] ?? null,
            ],
            user: $resolver,
            agent: $agent->agent_id,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );
    }

    /**
     * Revert approval request back to PENDING.
     */
    protected function revertToPending(ApprovalRequest $approval): void
    {
        $approval->update(['status' => 'PENDING', 'processing_started_at' => null]);
    }
}
