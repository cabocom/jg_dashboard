<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Services\Agents\AgentHttpClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExecutionController extends Controller
{
    /**
     * Trigger or resume a cognitive agent execution via jg_agente.
     */
    public function trigger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required_without:user_prompt|nullable|string|min:1',
            'user_prompt' => 'required_without:prompt|nullable|string|min:1',
            'agent_id' => 'nullable|string',
            'thread_id' => 'nullable|string',
            'parameters' => 'nullable|array',
            'async' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $user = $request->user();
        $targetAgent = null;
        $targetAgentId = $validated['agent_id'] ?? null;

        // 1. Resolver el agente objetivo
        if (!empty($targetAgentId)) {
            $targetAgent = Agent::find($targetAgentId);

            if (!$targetAgent) {
                return response()->json([
                    'error' => 'Not Found',
                    'message' => "El agente con ID '{$targetAgentId}' no existe.",
                ], 404);
            }

            if (!$targetAgent->isActive()) {
                return response()->json([
                    'error' => 'Agent Unavailable',
                    'message' => "El agente '{$targetAgent->name}' no está disponible para ejecuciones (estado actual: {$targetAgent->status}).",
                ], 400);
            }

            $client = AgentHttpClient::forAgent($targetAgent);
        } else {
            // Si no se especifica agente, intentamos resolver el primer agente activo registrado
            $targetAgent = Agent::active()->first();

            if ($targetAgent) {
                $targetAgentId = $targetAgent->agent_id;
                $client = AgentHttpClient::forAgent($targetAgent);
            } else {
                $client = new AgentHttpClient();
            }
        }

        // 2. Normalizar payload de entrada hacia el contrato formal AgentInput
        $executionId = (string) Str::uuid();
        $threadId = !empty($validated['thread_id']) ? $validated['thread_id'] : (string) Str::uuid();
        $correlationId = $request->header('X-Correlation-ID') ?: (string) Str::uuid();
        $prompt = $validated['prompt'] ?? $validated['user_prompt'];
        $isAsync = $request->boolean('async');

        $client->withCorrelationId($correlationId);

        $parameters = (array) ($validated['parameters'] ?? []);
        $statusCallbackUrl = url("/api/v1/internal/executions/{$executionId}/status-update");
        if ($isAsync) {
            $parameters['async'] = true;
            $parameters['status_callback_url'] = $statusCallbackUrl;
            $parameters['callback_url'] = $statusCallbackUrl;
        }

        $agentInput = [
            'execution_id' => $executionId,
            'thread_id' => $threadId,
            'correlation_id' => $correlationId,
            'prompt' => $prompt,
            'user_prompt' => $prompt,
            'parameters' => (object) $parameters,
            'user_context' => [
                'user_id' => (string) $user->id,
                'username' => $user->name ?? $user->email,
                'email' => $user->email,
                'role' => $user->role,
                'permissions' => method_exists($user, 'getPermissions')
                    ? $user->getPermissions()
                    : ($user->role === 'admin' ? ['*', 'docker:restart', 'docker:inspect', 'docker:list', 'admin'] : []),
            ],
            'metadata' => (object) array_merge((array) ($validated['metadata'] ?? []), [
                'correlation_id' => $correlationId,
                'status_callback_url' => $isAsync ? $statusCallbackUrl : null,
                'triggered_by' => $user->email,
                'user_role' => $user->role,
                'client_ip' => $request->ip(),
                'agent_id' => $targetAgentId,
            ]),
        ];

        // 3. Invocar al Execution Plane mediante AgentHttpClient
        $response = $client->triggerExecution($agentInput);

        // 4. Registrar evento de auditoría en el Dashboard
        AuditEvent::record(
            eventType: 'execution.triggered',
            actionSummary: "Ejecución iniciada: {$executionId} (thread: {$threadId})",
            payload: [
                'execution_id' => $executionId,
                'thread_id' => $threadId,
                'correlation_id' => $correlationId,
                'agent_id' => $targetAgentId,
                'async' => $isAsync,
                'success' => $response['success'],
                'status_code' => $response['status_code'],
            ],
            user: $user,
            agent: $targetAgentId,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        // 5. Manejo de error de comunicación o fallo en el Execution Plane
        if (!$response['success']) {
            $httpCode = match ($response['status_code']) {
                503 => 503,
                504 => 504,
                default => 502,
            };

            try {
                AgentTask::updateOrCreate(
                    ['task_id' => $executionId],
                    [
                        'thread_id' => $threadId,
                        'status' => 'FAILED',
                        'payload_json' => [
                            'agent_id' => $targetAgentId,
                            'prompt' => $prompt,
                            'user_prompt' => $prompt,
                            'username' => $user?->name ?? 'operator',
                            'user_context' => [
                                'user_id' => $user?->id,
                                'name' => $user?->name,
                                'email' => $user?->email,
                            ],
                        ],
                        'result_json' => [
                            'error' => $response['error'] ?? 'Error al comunicar con jg_agente',
                            'upstream_status' => $response['status_code'],
                        ],
                        'created_at' => now(),
                        'started_at' => now(),
                        'completed_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("[ExecutionController] No se pudo registrar AgentTask fallida: " . $e->getMessage());
            }

            return response()->json([
                'error' => 'Agent Gateway Error',
                'message' => $response['error'] ?? 'Error al comunicar con jg_agente',
                'execution_id' => $executionId,
                'thread_id' => $threadId,
                'correlation_id' => $correlationId,
                'agent_id' => $targetAgentId,
                'upstream_status' => $response['status_code'],
            ], $httpCode)->header('X-Correlation-ID', $correlationId);
        }

        $responseData = $response['data'] ?? [];
        $cognitiveSteps = $responseData['cognitive_steps']
            ?? $responseData['data']['cognitive_steps']
            ?? $responseData['data']['steps']
            ?? [];

        $planData = $responseData['data']['plan'] ?? $responseData['plan'] ?? [];
        if (is_array($planData)) {
            $planData['prompt'] = $prompt;
            $planData['cognitive_steps'] = $cognitiveSteps;
        }

        $requiresApproval = (bool) (
            ($responseData['requires_approval'] ?? false)
            || ($responseData['data']['requires_approval'] ?? false)
            || ($responseData['status'] ?? '') === 'waiting_approval'
            || ($responseData['data']['status'] ?? '') === 'waiting_approval'
            || !empty($responseData['pending_approval_id'])
            || !empty($responseData['data']['pending_approval_id'])
        );

        // 6. Si la ejecución queda detenida en HITL, persistir solicitud mediante HitlApprovalService
        if ($requiresApproval) {
            $effectiveThreadId = $responseData['thread_id'] ?? $threadId;
            $effectiveTaskId = $responseData['execution_id'] ?? $executionId;

            app(\App\Services\Hitl\HitlApprovalService::class)->registerPendingApproval([
                'thread_id' => $effectiveThreadId,
                'agent_id' => $targetAgentId,
                'task_id' => $effectiveTaskId,
                'prompt' => $prompt,
                'risk_level' => $responseData['data']['risk_assessment']['risk_level'] ?? $responseData['risk_level'] ?? 'HIGH',
                'proposed_plan' => $planData,
                'cognitive_steps' => $cognitiveSteps,
                'compensation_commands' => $responseData['data']['compensation_commands'] ?? $responseData['compensation_commands'] ?? [],
                'tools' => $responseData['tools'] ?? null,
            ], $user, $request->ip(), $request->userAgent());
        }

        // 7. Sincronizar / persistir la ejecución en agent_tasks para visibilidad unificada
        $effectiveThreadId = $responseData['thread_id'] ?? $threadId;
        $effectiveTaskId = $responseData['execution_id'] ?? $executionId;
        $taskStatus = match ($responseData['status'] ?? null) {
            'completed', 'success' => 'COMPLETED',
            'waiting_approval' => 'WAITING_APPROVAL',
            'in_progress' => 'PROCESSING',
            'failed', 'error' => 'FAILED',
            default => $isAsync ? 'QUEUED' : ($requiresApproval ? 'WAITING_APPROVAL' : 'COMPLETED'),
        };

        try {
            AgentTask::updateOrCreate(
                ['task_id' => $effectiveTaskId],
                [
                    'thread_id' => $effectiveThreadId,
                    'status' => $taskStatus,
                    'payload_json' => [
                        'agent_id' => $targetAgentId,
                        'prompt' => $prompt,
                        'user_prompt' => $prompt,
                        'username' => $user?->name ?? 'operator',
                        'user_context' => [
                            'user_id' => $user?->id,
                            'name' => $user?->name,
                            'email' => $user?->email,
                        ],
                    ],
                    'result_json' => $responseData,
                    'created_at' => now(),
                    'started_at' => now(),
                    'completed_at' => in_array($taskStatus, ['COMPLETED', 'FAILED']) ? now() : null,
                ]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[ExecutionController] No se pudo registrar AgentTask para task '{$effectiveTaskId}': " . $e->getMessage());
        }

        $statusCode = ($isAsync || ($responseData['status'] ?? '') === 'in_progress') ? 202 : 200;
        $effectiveCorrelationId = $responseData['correlation_id'] ?? $correlationId;

        return response()->json([
            'execution_id' => $responseData['execution_id'] ?? $executionId,
            'thread_id' => $responseData['thread_id'] ?? $threadId,
            'correlation_id' => $effectiveCorrelationId,
            'agent_id' => $targetAgentId,
            'status' => $responseData['status'] ?? ($isAsync ? 'in_progress' : 'completed'),
            'status_callback_url' => $isAsync ? $statusCallbackUrl : null,
            'requires_approval' => $requiresApproval,
            'pending_approval_id' => $responseData['pending_approval_id'] ?? null,
            'output' => $responseData['final_output'] ?? $responseData['output'] ?? $responseData['response'] ?? null,
            'steps' => $cognitiveSteps,
            'cognitive_steps' => $cognitiveSteps,
            'data' => $responseData,
            'latency_ms' => $response['latency_ms'],
        ], $statusCode)->header('X-Correlation-ID', $effectiveCorrelationId);
    }

    /**
     * Inspect execution state, step history and HITL checkpoints for a specific thread on a target agent.
     */
    public function show(Request $request, string $agentId, string $threadId): JsonResponse
    {
        $agent = Agent::find($agentId);

        if (!$agent) {
            return response()->json([
                'error' => 'Not Found',
                'message' => "El agente con ID '{$agentId}' no existe.",
            ], 404);
        }

        if ($agent->isPending()) {
            return response()->json([
                'error' => 'Agent Pending Registration',
                'message' => "El agente '{$agent->name}' aún se encuentra en estado PENDING y no ha completado su registro.",
            ], 400);
        }

        $client = AgentHttpClient::forAgent($agent);
        $response = $client->getExecutionStatus($threadId);

        if (!$response['success']) {
            if ($response['status_code'] === 404) {
                return response()->json([
                    'error' => 'Execution Not Found',
                    'message' => "No se encontró la ejecución con thread_id '{$threadId}' en el agente.",
                    'agent_id' => $agentId,
                    'thread_id' => $threadId,
                ], 404);
            }

            $httpCode = match ($response['status_code']) {
                503 => 503,
                504 => 504,
                default => 502,
            };

            return response()->json([
                'error' => 'Agent Gateway Error',
                'message' => $response['error'] ?? 'Error al consultar estado de la ejecución en jg_agente',
                'agent_id' => $agentId,
                'thread_id' => $threadId,
                'upstream_status' => $response['status_code'],
            ], $httpCode);
        }

        $data = $response['data'] ?? [];
        $localApproval = ApprovalRequest::with('resolver')->where('thread_id', $threadId)->latest()->first();

        // Sincronizar / actualizar el registro local en agent_tasks si existe
        try {
            $agentStatus = strtolower($data['status'] ?? '');
            $taskStatus = match ($agentStatus) {
                'completed', 'success', 'resolved' => 'COMPLETED',
                'waiting_approval', 'awaiting_approval' => 'WAITING_APPROVAL',
                'in_progress', 'observing', 'reasoning', 'planning', 'executing', 'verifying', 'diagnosing', 'rolling_back', 'idle' => 'PROCESSING',
                'failed', 'error' => 'FAILED',
                'cancelled', 'timeout' => 'CANCELLED',
                default => null,
            };

            if ($taskStatus) {
                $task = AgentTask::where('thread_id', $threadId)
                    ->orWhere('task_id', $data['execution_id'] ?? $threadId)
                    ->latest()
                    ->first();

                if ($task) {
                    $updates = ['status' => $taskStatus];
                    if (in_array($taskStatus, ['COMPLETED', 'FAILED', 'CANCELLED'])) {
                        if (!$task->completed_at) {
                            $updates['completed_at'] = !empty($data['completed_at'])
                                ? \Carbon\Carbon::parse($data['completed_at'])
                                : now();
                        }
                        if ($task->started_at && !$task->duration) {
                            $endTime = $updates['completed_at'] ?? now();
                            $updates['duration'] = round($endTime->diffInMilliseconds($task->started_at) / 1000, 2);
                        }
                    }
                    if (!empty($data['output']) || !empty($data['response']) || !empty($data['final_output'])) {
                        $currentResult = $task->result_json ?? [];
                        $updates['result_json'] = array_merge(
                            is_array($currentResult) ? $currentResult : [],
                            $data
                        );
                    }
                    $task->update($updates);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[ExecutionController@show] Error sincronizando AgentTask: " . $e->getMessage());
        }

        $unified = [
            'agent_id' => $agentId,
            'agent_name' => $agent->name,
            'thread_id' => $threadId,
            'execution_id' => $data['execution_id'] ?? null,
            'correlation_id' => $data['correlation_id'] ?? null,
            'status' => $data['status'] ?? 'unknown',
            'prompt' => $data['prompt'] ?? null,
            'output' => $data['response'] ?? $data['final_output'] ?? $data['output'] ?? null,
            'current_step' => $data['current_step'] ?? 0,
            'next_nodes' => $data['next_nodes'] ?? [],
            'requires_approval' => $data['requires_approval'] ?? false,
            'approval_status' => $data['approval_status'] ?? ($localApproval?->status ?? null),
            'pending_approval_id' => $data['pending_approval_id'] ?? null,
            'plan' => $data['plan'] ?? null,
            'steps' => $data['steps'] ?? [],
            'cognitive_steps' => $data['cognitive_steps'] ?? $data['steps'] ?? [],
            'hitl_governance' => $localApproval ? [
                'approval_id' => $localApproval->id,
                'status' => $localApproval->status,
                'risk_level' => $localApproval->risk_level,
                'justification' => $localApproval->justification,
                'resolved_at' => $localApproval->resolved_at?->toIso8601String(),
                'resolver' => $localApproval->resolver ? [
                    'id' => $localApproval->resolver->id,
                    'name' => $localApproval->resolver->name,
                    'email' => $localApproval->resolver->email,
                ] : null,
            ] : null,
            'data' => $data,
            'latency_ms' => $response['latency_ms'],
        ];

        $jsonResponse = response()->json($unified, 200);
        if (!empty($data['correlation_id'])) {
            $jsonResponse->header('X-Correlation-ID', $data['correlation_id']);
        }

        return $jsonResponse;
    }

    /**
     * Handle asynchronous execution status-update webhook (DASH-CODE-02 proxy/entrypoint).
     */
    public function statusUpdate(Request $request, string $executionId): JsonResponse
    {
        return app(InternalExecutionController::class)->statusUpdate($request, $executionId);
    }
}
