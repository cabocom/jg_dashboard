<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Services\Executions;

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\AuditEvent;
use App\Services\Hitl\HitlApprovalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class InternalExecutionWebhookProcessor
{
    public function resolveTaskStatus(string $statusRaw, string $default = 'COMPLETED'): string
    {
        return match (strtolower($statusRaw)) {
            'completed', 'success', 'resolved' => 'COMPLETED',
            'waiting_approval', 'awaiting_approval' => 'WAITING_APPROVAL',
            'cancelled', 'rejected', 'timeout' => 'CANCELLED',
            'failed', 'error' => 'FAILED',
            'processing', 'in_progress', 'observing', 'reasoning', 'planning', 'executing', 'verifying', 'diagnosing', 'rolling_back', 'queued' => 'PROCESSING',
            default => $default,
        };
    }

    public function upsertAgentTask(
        string $executionId,
        string $threadId,
        Agent $agent,
        string $taskStatus,
        array $responseData,
        ?Carbon $completedAt,
        ?string $prompt = null
    ): ?AgentTask {
        try {
            $task = AgentTask::where('task_id', $executionId)
                ->orWhere('thread_id', $threadId)
                ->latest()
                ->first();

            if ($task) {
                $existingResult = is_array($task->result_json) ? $task->result_json : [];
                $mergedResult = array_merge($existingResult, $responseData);
                $finalText = $responseData['final_output'] ?? $responseData['output'] ?? $responseData['response'] ?? null;
                if ($finalText && $finalText !== 'Ejecución aceptada y encolada en segundo plano.') {
                    $mergedResult['output'] = $finalText;
                    $mergedResult['response'] = $finalText;
                    $mergedResult['final_output'] = $finalText;
                }

                $freshSteps = $responseData['data']['cognitive_steps']
                    ?? $responseData['cognitive_steps']
                    ?? $responseData['data']['steps']
                    ?? $responseData['steps']
                    ?? null;
                if (is_array($freshSteps) && count($freshSteps) >= count($mergedResult['cognitive_steps'] ?? [])) {
                    $mergedResult['cognitive_steps'] = $freshSteps;
                    $mergedResult['steps'] = $freshSteps;
                }

                $updates = [
                    'status' => $taskStatus,
                    'result_json' => $mergedResult,
                ];

                if ($completedAt) {
                    $updates['completed_at'] = $completedAt;
                    if ($task->started_at) {
                        $updates['duration'] = round($completedAt->diffInMilliseconds($task->started_at) / 1000, 2);
                    }
                }

                $task->update($updates);

                return $task;
            }

            return AgentTask::create([
                'task_id' => $executionId,
                'thread_id' => $threadId,
                'agent_id' => $agent->agent_id,
                'status' => $taskStatus,
                'payload_json' => [
                    'agent_id' => $agent->agent_id,
                    'prompt' => $prompt ?? '',
                ],
                'result_json' => $responseData,
                'created_at' => now(),
                'started_at' => now(),
                'completed_at' => $completedAt,
            ]);
        } catch (\Throwable $e) {
            Log::warning("[InternalExecutionWebhookProcessor] Error actualizando AgentTask '{$executionId}': ".$e->getMessage());

            return null;
        }
    }

    public function handlePendingHitlApproval(
        bool $requiresApproval,
        string $executionId,
        string $threadId,
        Agent $agent,
        array $responseData,
        ?string $prompt,
        ?string $ip,
        ?string $userAgent
    ): void {
        if (!$requiresApproval) {
            return;
        }

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
                'prompt' => $prompt ?? 'Operación en espera de autorización humana',
                'risk_level' => $responseData['data']['risk_assessment']['risk_level'] ?? $responseData['risk_level'] ?? 'HIGH',
                'proposed_plan' => $planData,
                'cognitive_steps' => $cognitiveSteps,
                'compensation_commands' => $responseData['data']['compensation_commands'] ?? $responseData['compensation_commands'] ?? [],
                'tools' => $responseData['tools'] ?? null,
            ], null, $ip, $userAgent);
        } catch (\Throwable $e) {
            Log::warning("[InternalExecutionWebhookProcessor] Error registrando solicitud HITL para '{$executionId}': ".$e->getMessage());
        }
    }
}
