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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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

        if (! empty($tools) && ! isset($plan['tools_involved'])) {
            $plan['tools_involved'] = $tools;
        }

        if (! empty($data['prompt']) && ! isset($plan['prompt'])) {
            $plan['prompt'] = $data['prompt'];
        }

        if (! empty($data['cognitive_steps']) && ! isset($plan['cognitive_steps'])) {
            $plan['cognitive_steps'] = $data['cognitive_steps'];
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

        if (! empty($agentId)) {
            $query->where('agent_id', $agentId);
        }

        if (! empty($riskLevel)) {
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

        if (! empty($plan['summary']) && is_string($plan['summary'])) {
            return $plan['summary'];
        }

        if (! empty($plan['action']) && is_string($plan['action'])) {
            return $plan['action'];
        }

        if (! empty($plan['description']) && is_string($plan['description'])) {
            return $plan['description'];
        }

        if (! empty($plan['command']) && is_string($plan['command'])) {
            return "Ejecución de comando: {$plan['command']}";
        }

        if (! empty($plan['step']) && is_string($plan['step'])) {
            return "Paso del plan: {$plan['step']}";
        }

        $tools = $plan['tools_involved'] ?? ($plan['tools'] ?? []);
        if (! empty($tools) && is_array($tools)) {
            return 'Ejecución de herramientas: '.implode(', ', $tools);
        }

        return 'Plan de ejecución pendiente de revisión humana';
    }

    /**
     * Format an approval request into a standardized API payload.
     *
     * @return array<string, mixed>
     */
    public function formatApprovalItem(ApprovalRequest $approval): array
    {
        $plan = $approval->proposed_plan_json ?? [];
        $prompt = $plan['prompt'] ?? null;
        $cognitiveSteps = $plan['cognitive_steps'] ?? [];

        // Si el plan carece de prompt o de pasos cognitivos, sincronizar en tiempo real con jg_agente
        if ((empty($prompt) || empty($cognitiveSteps)) && $approval->agent && $approval->thread_id) {
            try {
                $client = AgentHttpClient::forAgent($approval->agent);
                $statusRes = $client->getExecutionStatus($approval->thread_id);
                if ($statusRes['success'] && ! empty($statusRes['data'])) {
                    $agentData = $statusRes['data'];
                    $fetchedPrompt = $agentData['prompt'] ?? ($agentData['goal'] ?? null);
                    $fetchedSteps = $agentData['cognitive_steps'] ?? ($agentData['steps'] ?? []);
                    $fetchedPlan = $agentData['plan'] ?? [];

                    $dirty = false;
                    if (empty($prompt) && ! empty($fetchedPrompt)) {
                        $prompt = $fetchedPrompt;
                        $plan['prompt'] = $fetchedPrompt;
                        $dirty = true;
                    }
                    if (empty($cognitiveSteps) && ! empty($fetchedSteps)) {
                        $cognitiveSteps = $fetchedSteps;
                        $plan['cognitive_steps'] = $fetchedSteps;
                        $dirty = true;
                    }
                    if (empty($plan['steps']) && ! empty($fetchedPlan['steps'])) {
                        $plan['steps'] = $fetchedPlan['steps'];
                        $dirty = true;
                    }
                    if (empty($plan['tools_involved']) && ! empty($fetchedPlan['steps'])) {
                        $planTools = array_filter(array_map(fn ($s) => $s['tool_name'] ?? null, $fetchedPlan['steps']));
                        if (! empty($planTools)) {
                            $plan['tools_involved'] = array_values($planTools);
                            $dirty = true;
                        }
                    }

                    if ($dirty) {
                        $approval->update(['proposed_plan_json' => $plan]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("[HitlApprovalService] Sincronización pasiva de plan omitida para thread '{$approval->thread_id}': ".$e->getMessage());
            }
        }

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
            'prompt' => $prompt,
            'tools_involved' => is_array($tools) ? array_values($tools) : [],
            'proposed_plan' => $plan,
            'cognitive_steps' => is_array($cognitiveSteps) ? array_values($cognitiveSteps) : [],
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
    public function approveApproval(
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
            Log::error("[HitlApprovalService] Excepción de red al delegar aprobación al agente '{$agent->agent_id}' para thread '{$approval->thread_id}'", [
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
                Log::info("[HitlApprovalService] Reconciliando aprobación {$approval->id} (hilo {$approval->thread_id}): {$reason}");
                $approval->approve($resolver, ($justification ? $justification.' ' : '')."[Reconciliado: {$reason}]");
                $freshApproval = $approval->fresh(['agent', 'resolver']);
                $this->syncAssociatedTask($approval, 'PROCESSING');

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
                        'approval' => $this->formatApprovalItem($freshApproval),
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
            Log::error("[HitlApprovalService] Error al delegar aprobación al agente '{$agent->agent_id}' para thread '{$approval->thread_id}'", [
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
        $this->syncAssociatedTask($approval, 'PROCESSING');

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

        Log::info("[HitlApprovalService] Aprobación concedida exitosamente para solicitud {$approval->id} (hilo {$approval->thread_id}) por usuario {$resolver->email}");

        return [
            'success' => true,
            'status_code' => 200,
            'data' => [
                'approval' => $this->formatApprovalItem($freshApproval),
                'agent_response' => $agentResponse['data'] ?? null,
            ],
        ];
    }

    /**
     * Reject a pending approval request, delegate rejection command to jg_agente and record audit log.
     *
     * @return array{
     *     success: bool,
     *     status_code: int,
     *     data?: array,
     *     error?: string
     * }
     */
    public function rejectApproval(
        int|string $approvalId,
        User $resolver,
        string $justification,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        if (empty(trim($justification))) {
            return [
                'success' => false,
                'status_code' => 422,
                'error' => 'El motivo o justificación del rechazo es estrictamente obligatorio.',
            ];
        }

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
                return ['status_code' => 400, 'error' => "El agente '{$agent->agent_id}' aún está en estado PENDING y no puede recibir órdenes de rechazo."];
            }

            // Transicionar a PROCESSING antes de invocar la API del agente para impedir rechazos concurrentes
            $record->update([
                'status' => 'PROCESSING',
                'processing_started_at' => now(),
            ]);

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

        // 2. Delegar el comando /reject hacia jg_agente a través de AgentHttpClient
        $client = $agent
            ? AgentHttpClient::forAgent($agent)
            : (new AgentHttpClient)->withAgentId($approval->agent_id);
        $payload = [
            'thread_id' => $approval->thread_id,
            'approved' => false,
            'rejected' => true,
            'notes' => $justification,
            'justification' => $justification,
            'reason' => $justification,
            'approver' => $resolver->name ?: $resolver->email,
            'user' => [
                'id' => $resolver->id,
                'name' => $resolver->name,
                'email' => $resolver->email,
                'role' => $resolver->role,
            ],
        ];

        try {
            $agentResponse = $client->rejectExecution($approval->thread_id, $payload);
        } catch (\Throwable $e) {
            // Reconciliar estado a PENDING ante fallo de red / excepción de transporte
            $approval->update([
                'status' => 'PENDING',
                'processing_started_at' => null,
            ]);
            Log::error("[HitlApprovalService] Excepción de red al delegar rechazo al agente '{$agent->agent_id}' para thread '{$approval->thread_id}'", [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status_code' => 500,
                'error' => 'Fallo de conexión al comunicar el rechazo con el agente: '.$e->getMessage(),
            ];
        }

        if (! $agentResponse['success']) {
            // Si el hilo en el agente ya concluyó (409) o expiró (404), reconciliar el estado localmente
            if (in_array($agentResponse['status_code'], [404, 409], true)) {
                $reason = $agentResponse['error'] ?? 'El hilo en el agente ya concluyó o no requiere aprobación activa';
                Log::info("[HitlApprovalService] Reconciliando rechazo para aprobación {$approval->id} (hilo {$approval->thread_id}): {$reason}");
                $approval->reject($resolver, $justification." [Reconciliado: {$reason}]");
                $freshApproval = $approval->fresh(['agent', 'resolver']);
                $this->syncAssociatedTask($approval, 'CANCELLED', $justification);

                AuditEvent::record(
                    eventType: 'hitl.rejected',
                    actionSummary: "Rechazo HITL reconciliado para hilo {$approval->thread_id} por {$resolver->email}: {$justification}",
                    payload: [
                        'approval_id' => $approval->id,
                        'thread_id' => $approval->thread_id,
                        'agent_id' => $agent->agent_id,
                        'resolver_id' => $resolver->id,
                        'justification' => $justification,
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
                        'approval' => $this->formatApprovalItem($freshApproval),
                        'agent_response' => $agentResponse['data'] ?? null,
                        'reconciled' => true,
                        'message' => 'Solicitud reconciliada y rechazada (el hilo en el agente ya había concluido).',
                    ],
                ];
            }

            // Reconciliar estado a PENDING ante error retornado por el agente
            $approval->update([
                'status' => 'PENDING',
                'processing_started_at' => null,
            ]);
            Log::error("[HitlApprovalService] Error al delegar rechazo al agente '{$agent->agent_id}' para thread '{$approval->thread_id}'", [
                'status_code' => $agentResponse['status_code'],
                'error' => $agentResponse['error'],
            ]);

            return [
                'success' => false,
                'status_code' => $agentResponse['status_code'],
                'error' => 'Error al comunicar el rechazo con el agente: '.($agentResponse['error'] ?? 'Error desconocido'),
            ];
        }

        // 3. Marcar solicitud como rechazada
        $approval->reject($resolver, $justification);
        $freshApproval = $approval->fresh(['agent', 'resolver']);
        $this->syncAssociatedTask($approval, 'CANCELLED', $justification);

        // Registrar evento de auditoría inmutable
        AuditEvent::record(
            eventType: 'hitl.rejected',
            actionSummary: "Rechazo HITL registrado para hilo {$approval->thread_id} por {$resolver->email}: {$justification}",
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

        Log::info("[HitlApprovalService] Rechazo registrado exitosamente para solicitud {$approval->id} (hilo {$approval->thread_id}) por usuario {$resolver->email}");

        return [
            'success' => true,
            'status_code' => 200,
            'data' => [
                'approval' => $this->formatApprovalItem($freshApproval),
                'agent_response' => $agentResponse['data'] ?? null,
            ],
        ];
    }

    /**
     * Sincroniza el estado de la tarea AgentTask asociada tras una resolución HITL.
     */
    protected function syncAssociatedTask(ApprovalRequest $approval, string $targetStatus, ?string $reason = null): void
    {
        try {
            $task = AgentTask::where(function ($q) use ($approval) {
                if (! empty($approval->task_id)) {
                    $q->where('task_id', $approval->task_id);
                }
                if (! empty($approval->thread_id)) {
                    $q->orWhere('thread_id', $approval->thread_id);
                }
            })->latest()->first();

            if (! $task) {
                return;
            }

            if ($targetStatus === 'CANCELLED') {
                $updates = [
                    'status' => 'CANCELLED',
                    'completed_at' => now(),
                ];
                if ($reason) {
                    $res = is_array($task->result_json) ? $task->result_json : [];
                    $res['rejection_reason'] = $reason;
                    $updates['result_json'] = $res;
                }
                $task->update($updates);
                Log::info("[HitlApprovalService] AgentTask '{$task->task_id}' sincronizada a CANCELLED tras rechazo HITL");
            } elseif ($targetStatus === 'PROCESSING') {
                if ($task->status === 'WAITING_APPROVAL' || $task->status === 'QUEUED') {
                    $task->update(['status' => 'PROCESSING']);
                    Log::info("[HitlApprovalService] AgentTask '{$task->task_id}' reanudada a PROCESSING tras aprobación HITL");
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[HitlApprovalService] Error al sincronizar AgentTask para aprobación {$approval->id}: ".$e->getMessage());
        }
    }

    /**
     * Reconcile stuck approval requests in PROCESSING status that exceeded the TTL threshold.
     * Restores them atomically to PENDING with an immutable audit event trail.
     *
     * @return array{
     *     reconciled_count: int,
     *     reconciled_ids: array<int|string>
     * }
     */
    public function reconcileStaleProcessingApprovals(int $ttlSeconds = 120): array
    {
        $threshold = now()->subSeconds($ttlSeconds);
        $reconciledIds = [];

        $staleApprovals = ApprovalRequest::where('status', 'PROCESSING')
            ->whereNotNull('processing_started_at')
            ->where('processing_started_at', '<=', $threshold)
            ->get();

        foreach ($staleApprovals as $approval) {
            DB::transaction(function () use ($approval, $ttlSeconds, &$reconciledIds) {
                /** @var ApprovalRequest|null $locked */
                $locked = ApprovalRequest::where('id', $approval->id)
                    ->lockForUpdate()
                    ->first();

                if (! $locked || $locked->status !== 'PROCESSING') {
                    return;
                }

                $startedAtIso = $locked->processing_started_at?->toIso8601String();

                $locked->update([
                    'status' => 'PENDING',
                    'processing_started_at' => null,
                ]);

                AuditEvent::record(
                    eventType: 'hitl.reconciled_stale_processing',
                    actionSummary: "Solicitud HITL {$locked->id} (hilo {$locked->thread_id}) restaurada a PENDING tras superar TTL de procesamiento ({$ttlSeconds}s)",
                    payload: [
                        'approval_id' => $locked->id,
                        'thread_id' => $locked->thread_id,
                        'agent_id' => $locked->agent_id,
                        'previous_status' => 'PROCESSING',
                        'new_status' => 'PENDING',
                        'processing_started_at' => $startedAtIso,
                        'ttl_seconds' => $ttlSeconds,
                        'reconciled_at' => now()->toIso8601String(),
                    ]
                );

                $reconciledIds[] = $locked->id;
            });
        }

        return [
            'reconciled_count' => count($reconciledIds),
            'reconciled_ids' => $reconciledIds,
        ];
    }
}
