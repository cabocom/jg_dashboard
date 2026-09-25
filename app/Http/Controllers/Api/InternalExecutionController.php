<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\AuditEvent;
use App\Services\Hitl\HitlApprovalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InternalExecutionController extends Controller
{
    /**
     * Process asynchronous execution completion webhook sent by jg_agente.
     */
    public function complete(Request $request, string $executionId): JsonResponse
    {
        // 1. Extract Agent ID (Header X-Agent-ID or body agent_id)
        $agentId = $request->header('X-Agent-ID') ?: $request->input('agent_id');

        // 2. Extract Permanent Secret (Bearer token, X-Agent-Secret header, or body permanent_secret)
        $secret = $request->bearerToken()
            ?: ($request->header('X-Agent-Secret') ?: $request->input('permanent_secret'));

        if (empty($agentId) || empty($secret)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Identificador de agente o credencial permanente no provistos.',
            ], 401);
        }

        $agent = Agent::find(trim($agentId));
        if (!$agent) {
            return response()->json([
                'error' => 'Not Found',
                'message' => "Agente con ID '{$agentId}' no encontrado.",
            ], 404);
        }

        if (!$agent->verifyPermanentSecret(trim($secret))) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Credencial de agente inválida.',
            ], 401);
        }

        // 3. Process execution payload
        $threadId = $request->input('thread_id') ?? $executionId;
        $statusRaw = strtolower((string) $request->input('status', 'completed'));
        $responseData = $request->all();

        $taskStatus = match ($statusRaw) {
            'completed', 'success', 'resolved' => 'COMPLETED',
            'waiting_approval', 'awaiting_approval' => 'WAITING_APPROVAL',
            'cancelled', 'rejected' => 'CANCELLED',
            'failed', 'error' => 'FAILED',
            default => 'COMPLETED',
        };

        $requiresApproval = (bool) (
            $request->boolean('requires_approval')
            || $taskStatus === 'WAITING_APPROVAL'
            || !empty($request->input('pending_approval_id'))
        );

        $completedAt = in_array($taskStatus, ['COMPLETED', 'FAILED', 'CANCELLED'])
            ? (!empty($responseData['completed_at']) ? Carbon::parse($responseData['completed_at']) : now())
            : null;

        // 4. Update or create AgentTask
        try {
            $task = AgentTask::where('task_id', $executionId)
                ->orWhere('thread_id', $threadId)
                ->latest()
                ->first();

            if ($task) {
                $updates = [
                    'status' => $taskStatus,
                    'result_json' => array_merge(
                        is_array($task->result_json) ? $task->result_json : [],
                        $responseData
                    ),
                ];

                if ($completedAt) {
                    $updates['completed_at'] = $completedAt;
                    if ($task->started_at) {
                        $updates['duration'] = round($completedAt->diffInMilliseconds($task->started_at) / 1000, 2);
                    }
                }

                $task->update($updates);
            } else {
                $task = AgentTask::create([
                    'task_id' => $executionId,
                    'thread_id' => $threadId,
                    'agent_id' => $agent->agent_id,
                    'status' => $taskStatus,
                    'payload_json' => [
                        'agent_id' => $agent->agent_id,
                        'prompt' => $request->input('prompt') ?? $request->input('response') ?? '',
                    ],
                    'result_json' => $responseData,
                    'created_at' => now(),
                    'started_at' => now(),
                    'completed_at' => $completedAt,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("[InternalExecutionController] Error actualizando AgentTask '{$executionId}': " . $e->getMessage());
        }

        // 5. If HITL approval is required, register approval request
        if ($requiresApproval) {
            try {
                $cognitiveSteps = $responseData['cognitive_steps']
                    ?? $responseData['data']['cognitive_steps']
                    ?? $responseData['data']['steps']
                    ?? [];

                $planData = $responseData['data']['plan'] ?? $responseData['plan'] ?? [];

                app(HitlApprovalService::class)->registerPendingApproval([
                    'thread_id' => $threadId,
                    'agent_id' => $agent->agent_id,
                    'task_id' => $executionId,
                    'prompt' => $request->input('prompt') ?? 'Operación en espera de autorización humana',
                    'risk_level' => $responseData['data']['risk_assessment']['risk_level'] ?? $responseData['risk_level'] ?? 'HIGH',
                    'proposed_plan' => $planData,
                    'cognitive_steps' => $cognitiveSteps,
                    'compensation_commands' => $responseData['data']['compensation_commands'] ?? $responseData['compensation_commands'] ?? [],
                    'tools' => $responseData['tools'] ?? null,
                ], null, $request->ip(), $request->userAgent());
            } catch (\Throwable $e) {
                Log::warning("[InternalExecutionController] Error registrando solicitud HITL para '{$executionId}': " . $e->getMessage());
            }
        }

        // 6. Record Audit Event
        AuditEvent::record(
            eventType: 'execution.webhook_completed',
            actionSummary: "Notificación de finalización asíncrona procesada para tarea: {$executionId} ({$taskStatus})",
            payload: [
                'execution_id' => $executionId,
                'thread_id' => $threadId,
                'status' => $taskStatus,
                'agent_id' => $agent->agent_id,
                'requires_approval' => $requiresApproval,
            ],
            agent: $agent->agent_id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Notificación de finalización procesada exitosamente.',
            'execution_id' => $executionId,
            'task_status' => $taskStatus,
        ], 200);
    }
}
