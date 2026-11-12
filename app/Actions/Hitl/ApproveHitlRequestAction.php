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
use App\Models\AgentTask;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Agents\AgentHttpClient;
use App\Services\Hitl\HitlApprovalFormatter;
use App\Services\Hitl\HitlTaskSynchronizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApproveHitlRequestAction
{
    public function __construct(
        protected HitlApprovalFormatter $formatter = new HitlApprovalFormatter,
        protected HitlTaskSynchronizer $taskSynchronizer = new HitlTaskSynchronizer
    ) {}

    /**
     * Approve a pending approval request, delegate command to jg_agente and record audit log.
     *
     * @return array{
     *     success: bool,
     *     status_code: int,
     *     data?: array,
     *     error?: string
     * }
     */
    public function execute(
        int|string $approvalId,
        User $resolver,
        ?string $justification = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        // 1. Bloqueo pesimista con lockForUpdate dentro de transacción para garantizar concurrencia segura
        $lockResult = DB::transaction(function () use ($approvalId) {
            /** @var ApprovalRequest|null $record */
            $record = ApprovalRequest::with(['agent', 'resolver'])
                ->where('id', $approvalId)
                ->lockForUpdate()
                ->first();

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
            if (! $agent) {
                return ['status_code' => 400, 'error' => "No se encontró el agente registrado asociado a la solicitud '{$approvalId}'."];
            }

            if ($agent->status === 'PENDING') {
                return ['status_code' => 400, 'error' => "El agente '{$agent->agent_id}' aún está en estado PENDING y no puede recibir aprobaciones."];
            }

            // Transicionar a PROCESSING para bloquear dobles aprobaciones concurrentes (registrando timestamp)
            $record->update([
                'status' => 'PROCESSING',
                'processing_started_at' => now(),
            ]);

            // Sincronizar inmediatamente la tarea asociada a PROCESSING para evitar que el polling concurrente la vea en WAITING_APPROVAL
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

        if ($lockResult['status_code'] !== 200) {
            return [
                'success' => false,
                'status_code' => $lockResult['status_code'],
                'error' => $lockResult['error'],
            ];
        }

        /** @var ApprovalRequest $approval */
        $approval = $lockResult['record'];
        $agent = $approval->agent
            ?? Agent::where('agent_id', $approval->agent_id)->first()
            ?? Agent::active()->first();

        // 2. Delegar el comando /approve hacia jg_agente a través de AgentHttpClient
        $client = $agent
            ? AgentHttpClient::forAgent($agent)
            : (new AgentHttpClient)->withAgentId($approval->agent_id);
        $payload = [
            'thread_id' => $approval->thread_id,
            'approved' => true,
            'notes' => $justification ?: 'Aprobado por operador',
            'justification' => $justification,
            'approver' => $resolver->name ?: $resolver->email,
            'user' => [
                'id' => $resolver->id,
                'name' => $resolver->name,
                'email' => $resolver->email,
                'role' => $resolver->role,
            ],
        ];

        try {
            $agentResponse = $client->approveExecution($approval->thread_id, $payload);
        } catch (\Throwable $e) {
            // Reconciliar estado a PENDING ante fallo de red / excepción de transporte
            $approval->update([
                'status' => 'PENDING',
                'processing_started_at' => null,
            ]);
            Log::error("[ApproveHitlRequestAction] Excepción de red al delegar aprobación al agente '{$agent->agent_id}' para thread '{$approval->thread_id}'", [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status_code' => 500,
                'error' => 'Fallo de conexión al comunicar la aprobación con el agente: '.$e->getMessage(),
            ];
        }

        if (! $agentResponse['success']) {
            // Si el hilo en el agente ya concluyó (409) o expiró (404), reconciliar el estado localmente
            if (in_array($agentResponse['status_code'], [404, 409], true)) {
                $reason = $agentResponse['error'] ?? 'El hilo en el agente ya concluyó o no requiere aprobación activa';
                Log::info("[ApproveHitlRequestAction] Reconciliando aprobación {$approval->id} (hilo {$approval->thread_id}): {$reason}");
                $approval->approve($resolver, ($justification ? $justification.' ' : '')."[Reconciliado: {$reason}]");
                $freshApproval = $approval->fresh(['agent', 'resolver']);
                $this->taskSynchronizer->syncAssociatedTask($approval, 'PROCESSING');

                AuditEvent::record(
                    eventType: 'hitl.approved',
                    actionSummary: "Aprobación HITL reconciliada para hilo {$approval->thread_id} por {$resolver->email}",
                    payload: [
                        'approval_id' => $approval->id,
                        'thread_id' => $approval->thread_id,
                        'agent_id' => $agent->agent_id,
                        'resolver_id' => $resolver->id,
                        'reconciled' => true,
                        'agent_response' => $agentResponse['data'] ?? null,
                    ],
                    user: $resolver,
                    agent: $agent->agent_id,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent
                );

                return [
                    'success' => true,
                    'status_code' => 200,
                    'data' => [
                        'approval' => $this->formatter->formatApprovalItem($freshApproval),
                        'agent_response' => $agentResponse['data'] ?? null,
                        'reconciled' => true,
                        'message' => 'Solicitud reconciliada y aprobada (el hilo en el agente ya había concluido).',
                    ],
                ];
            }

            // Reconciliar estado a PENDING ante otro tipo de error retornado por el agente
            $approval->update([
                'status' => 'PENDING',
                'processing_started_at' => null,
            ]);
            Log::error("[ApproveHitlRequestAction] Error al delegar aprobación al agente '{$agent->agent_id}' para thread '{$approval->thread_id}'", [
                'status_code' => $agentResponse['status_code'],
                'error' => $agentResponse['error'],
            ]);

            return [
                'success' => false,
                'status_code' => $agentResponse['status_code'],
                'error' => 'Error al comunicar la aprobación con el agente: '.($agentResponse['error'] ?? 'Error desconocido'),
            ];
        }

        // 3. Marcar solicitud como aprobada
        $approval->approve($resolver, $justification);
        $freshApproval = $approval->fresh(['agent', 'resolver']);
        $respData = $agentResponse['data'] ?? [];
        $respStatus = strtolower($respData['status'] ?? '');
        $finalStatus = match ($respStatus) {
            'completed', 'success', 'resolved' => 'COMPLETED',
            'failed', 'error' => 'FAILED',
            'cancelled' => 'CANCELLED',
            default => 'PROCESSING',
        };
        $this->taskSynchronizer->syncAssociatedTask($approval, $finalStatus, null, $respData);

        // Registrar evento de auditoría
        AuditEvent::record(
            eventType: 'hitl.approved',
            actionSummary: "Aprobación HITL concedida para hilo {$approval->thread_id} por {$resolver->email}",
            payload: [
                'approval_id' => $approval->id,
                'thread_id' => $approval->thread_id,
                'agent_id' => $agent->agent_id,
                'resolver_id' => $resolver->id,
                'resolver_email' => $resolver->email,
                'justification' => $justification,
                'risk_level' => $approval->risk_level,
                'agent_response' => $agentResponse['data'] ?? null,
            ],
            user: $resolver,
            agent: $agent->agent_id,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        Log::info("[ApproveHitlRequestAction] Aprobación concedida exitosamente para solicitud {$approval->id} (hilo {$approval->thread_id}) por usuario {$resolver->email}");

        return [
            'success' => true,
            'status_code' => 200,
            'data' => [
                'approval' => $this->formatter->formatApprovalItem($freshApproval),
                'agent_response' => $agentResponse['data'] ?? null,
            ],
        ];
    }
}

