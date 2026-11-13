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
use App\Models\AuditEvent;
use App\Services\Executions\InternalExecutionWebhookProcessor;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalExecutionController extends Controller
{
    public function __construct(
        protected InternalExecutionWebhookProcessor $processor = new InternalExecutionWebhookProcessor()
    ) {}

    /**
     * Process asynchronous execution completion webhook sent by jg_agente.
     */
    public function complete(Request $request, string $executionId): JsonResponse
    {
        $agentId = $request->header('X-Agent-ID') ?: $request->input('agent_id');
        $secret = $request->bearerToken()
            ?: ($request->header('X-Agent-Secret') ?: $request->input('permanent_secret'));

        if (empty($agentId) || empty($secret)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Identificador de agente o credencial permanente no provistos.',
            ], 401);
        }

        $agent = Agent::find(trim((string) $agentId));
        if (! $agent) {
            return response()->json([
                'error' => 'Not Found',
                'message' => "Agente con ID '{$agentId}' no encontrado.",
            ], 404);
        }

        if (! $agent->verifyPermanentSecret(trim((string) $secret))) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Credencial de agente inválida.',
            ], 401);
        }

        $threadId = (string) ($request->input('thread_id') ?? $executionId);
        $taskStatus = $this->processor->resolveTaskStatus((string) $request->input('status', 'completed'), 'COMPLETED');
        $responseData = $request->all();

        $requiresApproval = (bool) (
            $request->boolean('requires_approval')
            || $taskStatus === 'WAITING_APPROVAL'
            || ! empty($request->input('pending_approval_id'))
        );

        $completedAt = in_array($taskStatus, ['COMPLETED', 'FAILED', 'CANCELLED'])
            ? (! empty($responseData['completed_at']) ? Carbon::parse($responseData['completed_at']) : now())
            : null;

        $prompt = $request->input('prompt') ?? $request->input('response');
        $this->processor->upsertAgentTask($executionId, $threadId, $agent, $taskStatus, $responseData, $completedAt, $prompt);
        $this->processor->handlePendingHitlApproval($requiresApproval, $executionId, $threadId, $agent, $responseData, $prompt, $request->ip(), $request->userAgent());

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

    /**
     * Process asynchronous execution status update webhook sent by jg_agente.
     * Authenticated via HMAC-SHA256 (agent.hmac middleware).
     */
    public function statusUpdate(Request $request, string $executionId): JsonResponse
    {
        $agent = $request->attributes->get('authenticated_agent');

        if (! $agent) {
            $agentId = $request->header('X-Agent-ID') ?: $request->input('agent_id');
            if ($agentId) {
                $agent = Agent::find(trim((string) $agentId));
            }
        }

        if (! $agent) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Agente autenticado no encontrado.',
            ], 401);
        }

        $threadId = (string) ($request->input('thread_id') ?? $executionId);
        $taskStatus = $this->processor->resolveTaskStatus((string) $request->input('status', 'processing'), 'PROCESSING');
        $responseData = $request->all();

        $requiresApproval = (bool) (
            $request->boolean('requires_approval')
            || $taskStatus === 'WAITING_APPROVAL'
            || ! empty($request->input('pending_approval_id'))
        );

        $completedAt = in_array($taskStatus, ['COMPLETED', 'FAILED', 'CANCELLED'])
            ? (! empty($responseData['completed_at']) ? Carbon::parse($responseData['completed_at']) : now())
            : null;

        $prompt = $request->input('prompt') ?? $request->input('response');
        $this->processor->upsertAgentTask($executionId, $threadId, $agent, $taskStatus, $responseData, $completedAt, $prompt);
        $this->processor->handlePendingHitlApproval($requiresApproval, $executionId, $threadId, $agent, $responseData, $prompt, $request->ip(), $request->userAgent());

        AuditEvent::record(
            eventType: 'execution.status_update',
            actionSummary: "Actualización de estado asíncrona procesada para tarea: {$executionId} ({$taskStatus})",
            payload: [
                'execution_id' => $executionId,
                'thread_id' => $threadId,
                'status' => $taskStatus,
                'agent_id' => $agent->agent_id,
                'requires_approval' => $requiresApproval,
                'current_step' => $request->input('current_step') ?? $request->input('step'),
                'node' => $request->input('node') ?? $request->input('current_node'),
            ],
            agent: $agent->agent_id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Actualización de estado procesada exitosamente.',
            'execution_id' => $executionId,
            'thread_id' => $threadId,
            'task_status' => $taskStatus,
            'requires_approval' => $requiresApproval,
        ], 200);
    }
}
