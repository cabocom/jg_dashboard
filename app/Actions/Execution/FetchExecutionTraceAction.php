<?php

declare(strict_types=1);

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Actions\Execution;

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\ApprovalRequest;
use App\Services\Agents\AgentHttpClient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FetchExecutionTraceAction
{
    public function execute(string $agentId, string $threadId): JsonResponse
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

        $localTask = AgentTask::where('thread_id', $threadId)
            ->orWhere('task_id', $threadId)
            ->latest()
            ->first();

        $localApproval = ApprovalRequest::with('resolver')->where('thread_id', $threadId)->latest()->first();
        [$data, $latencyMs, $errorResponse] = $this->resolveExecutionData($agent, $threadId, $localTask);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $taskStatus = $this->syncLocalAgentTask($localTask, $data);
        $cognitiveSteps = $this->resolveCognitiveSteps($data, $localTask);
        $resolvedOutput = $this->resolveOutput($data, $localTask, $taskStatus);

        return $this->buildUnifiedResponse(
            agent: $agent,
            threadId: $threadId,
            data: $data,
            localTask: $localTask,
            localApproval: $localApproval,
            cognitiveSteps: $cognitiveSteps,
            resolvedOutput: $resolvedOutput,
            latencyMs: $latencyMs
        );
    }

    protected function resolveExecutionData(Agent $agent, string $threadId, ?AgentTask $localTask): array
    {
        $localResult = is_array($localTask?->result_json) ? $localTask->result_json : [];
        $hasTerminalOutput = !empty($localResult['final_output'])
            || (!empty($localResult['output']) && $localResult['output'] !== 'Ejecución aceptada y encolada en segundo plano.')
            || (!empty($localResult['response']) && $localResult['response'] !== 'Ejecución aceptada y encolada en segundo plano.');

        $isTerminalLocal = $localTask && in_array($localTask->status, ['COMPLETED', 'FAILED', 'CANCELLED']) && $hasTerminalOutput;

        if ($isTerminalLocal) {
            $data = $localResult;
            if (!empty($data['response_data']) && is_array($data['response_data'])) {
                $data = array_merge($data['response_data'], $data);
            }
            return [$data, null, null];
        }

        $client = AgentHttpClient::forAgent($agent);
        $response = $client->getExecutionStatus($threadId);

        if ($response['success']) {
            return [$response['data'] ?? [], $response['latency_ms'] ?? null, null];
        }

        if ($localTask) {
            return [$localResult, null, null];
        }

        if ($response['status_code'] === 404) {
            return [null, null, response()->json([
                'error' => 'Execution Not Found',
                'message' => "No se encontró la ejecución con thread_id '{$threadId}' en el agente.",
                'agent_id' => $agent->agent_id,
                'thread_id' => $threadId,
            ], 404)];
        }

        $httpCode = match ($response['status_code']) {
            503 => 503,
            504 => 504,
            default => 502,
        };

        return [null, null, response()->json([
            'error' => 'Agent Gateway Error',
            'message' => $response['error'] ?? 'Error al consultar estado de la ejecución en jg_agente',
            'agent_id' => $agent->agent_id,
            'thread_id' => $threadId,
            'upstream_status' => $response['status_code'],
        ], $httpCode)];
    }

    protected function syncLocalAgentTask(?AgentTask $localTask, array $data): ?string
    {
        if (!$localTask) {
            return null;
        }

        $taskStatus = null;
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
                $updates = ['status' => $taskStatus];
                if (in_array($taskStatus, ['COMPLETED', 'FAILED', 'CANCELLED']) && !$localTask->completed_at) {
                    $updates['completed_at'] = !empty($data['completed_at'])
                        ? Carbon::parse($data['completed_at'])
                        : now();
                }

                if (!empty($data['output']) || !empty($data['response']) || !empty($data['final_output'])) {
                    $currentResult = is_array($localTask->result_json) ? $localTask->result_json : [];
                    $mergedResult = array_merge($currentResult, $data);
                    $nestedSteps = $data['data']['cognitive_steps'] ?? $data['cognitive_steps'] ?? $data['data']['steps'] ?? $data['steps'] ?? null;
                    if (is_array($nestedSteps) && count($nestedSteps) >= count($mergedResult['cognitive_steps'] ?? [])) {
                        $mergedResult['cognitive_steps'] = $nestedSteps;
                        $mergedResult['steps'] = $nestedSteps;
                    }
                    $updates['result_json'] = $mergedResult;
                }
                $localTask->update($updates);
            }
        } catch (\Throwable $e) {
            Log::warning("[FetchExecutionTraceAction] Error sincronizando AgentTask: " . $e->getMessage());
        }

        return $taskStatus;
    }

    protected function resolveCognitiveSteps(array $data, ?AgentTask $localTask): array
    {
        $stepCandidates = [
            $data['data']['cognitive_steps'] ?? null,
            $data['cognitive_steps'] ?? null,
            $data['data']['steps'] ?? null,
            $data['steps'] ?? null,
            $data['trace_logs'] ?? null,
            $localTask?->trace_history_json ?? null,
        ];

        $cognitiveSteps = [];
        foreach ($stepCandidates as $candidate) {
            if (is_array($candidate) && count($candidate) > count($cognitiveSteps)) {
                $cognitiveSteps = $candidate;
            }
        }

        $effectiveStatus = $localTask?->status ?? ($data['status'] ?? 'COMPLETED');
        if (strtoupper((string) $effectiveStatus) !== 'WAITING_APPROVAL' && !empty($cognitiveSteps)) {
            $isCancelled = strtoupper((string) $effectiveStatus) === 'CANCELLED';
            $cognitiveSteps = array_map(function ($step) use ($isCancelled) {
                if (is_array($step) && ($step['status'] ?? '') === 'waiting_approval') {
                    $step['status'] = $isCancelled ? 'failed' : 'completed';
                }
                return $step;
            }, $cognitiveSteps);
        }

        return $cognitiveSteps;
    }

    protected function resolveOutput(array $data, ?AgentTask $localTask, ?string $taskStatus): mixed
    {
        $resolvedOutput = $data['final_output'] ?? $data['output'] ?? $data['response'] ?? null;
        $isCompletedOrFailed = $taskStatus === 'COMPLETED' || in_array($localTask?->status, ['COMPLETED', 'FAILED']);

        if ($isCompletedOrFailed && !empty($data['final_output'])) {
            return $data['final_output'];
        }

        if ($isCompletedOrFailed && $resolvedOutput === 'Ejecución aceptada y encolada en segundo plano.') {
            return $data['final_output'] ?? $data['output'] ?? $data['data']['output'] ?? null;
        }

        return $resolvedOutput;
    }

    protected function buildUnifiedResponse(
        Agent $agent,
        string $threadId,
        array $data,
        ?AgentTask $localTask,
        ?ApprovalRequest $localApproval,
        array $cognitiveSteps,
        mixed $resolvedOutput,
        ?float $latencyMs
    ): JsonResponse {
        $effectiveStatus = $localTask?->status ?? ($data['status'] ?? 'COMPLETED');

        $unified = [
            'agent_id' => $agent->agent_id,
            'agent_name' => $agent->name ?? 'Agente Conversacional',
            'thread_id' => $threadId,
            'execution_id' => $data['execution_id'] ?? $localTask?->task_id ?? $threadId,
            'correlation_id' => $data['correlation_id'] ?? $localTask?->payload_json['correlation_id'] ?? null,
            'status' => $effectiveStatus,
            'prompt' => $localTask?->prompt ?? ($data['prompt'] ?? ($data['data']['prompt'] ?? null)),
            'output' => $resolvedOutput,
            'user' => $localTask?->user_name ?? 'Sistema',
            'current_step' => $data['current_step'] ?? count($cognitiveSteps),
            'next_nodes' => $data['next_nodes'] ?? [],
            'requires_approval' => $data['requires_approval'] ?? false,
            'approval_status' => $data['approval_status'] ?? ($localApproval?->status ?? null),
            'pending_approval_id' => $data['pending_approval_id'] ?? null,
            'plan' => $data['plan'] ?? null,
            'steps' => $cognitiveSteps,
            'cognitive_steps' => $cognitiveSteps,
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
            'latency_ms' => $latencyMs,
            'duration' => $localTask?->duration,
        ];

        $jsonResponse = response()->json($unified, 200);
        if (!empty($data['correlation_id'])) {
            $jsonResponse->header('X-Correlation-ID', $data['correlation_id']);
        }

        return $jsonResponse;
    }
}

