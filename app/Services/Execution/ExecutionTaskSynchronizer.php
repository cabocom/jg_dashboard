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

namespace App\Services\Execution;

use App\DTO\Execution\TriggerExecutionInput;
use App\Models\AgentTask;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ExecutionTaskSynchronizer
{
    public function recordFailedTask(
        string $executionId,
        string $threadId,
        ?string $agentId,
        TriggerExecutionInput $input,
        ?User $user,
        array $response
    ): void {
        try {
            AgentTask::updateOrCreate(
                ['task_id' => $executionId],
                [
                    'thread_id' => $threadId,
                    'status' => 'FAILED',
                    'payload_json' => [
                        'agent_id' => $agentId,
                        'prompt' => $input->prompt,
                        'user_prompt' => $input->prompt,
                        'username' => $input->operatorHeader ?: ($user?->name ?? 'operator'),
                        'user_context' => [
                            'user_id' => $user?->id,
                            'name' => $input->operatorHeader ?: $user?->name,
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
            Log::warning("[ExecutionTaskSynchronizer] No se pudo registrar AgentTask fallida: " . $e->getMessage());
        }
    }

    public function recordExecutionTask(
        TriggerExecutionInput $input,
        ?User $user,
        ?string $agentId,
        string $taskId,
        string $threadId,
        array $responseData,
        bool $requiresApproval
    ): void {
        $taskStatus = match ($responseData['status'] ?? null) {
            'completed', 'success' => 'COMPLETED',
            'waiting_approval' => 'WAITING_APPROVAL',
            'in_progress' => 'PROCESSING',
            'failed', 'error' => 'FAILED',
            default => $input->isAsync ? 'QUEUED' : ($requiresApproval ? 'WAITING_APPROVAL' : 'COMPLETED'),
        };

        try {
            AgentTask::updateOrCreate(
                ['task_id' => $taskId],
                [
                    'thread_id' => $threadId,
                    'status' => $taskStatus,
                    'payload_json' => [
                        'agent_id' => $agentId,
                        'prompt' => $input->prompt,
                        'user_prompt' => $input->prompt,
                        'username' => $input->operatorHeader ?: ($user?->name ?? 'operator'),
                        'user_context' => [
                            'user_id' => $user?->id,
                            'name' => $input->operatorHeader ?: $user?->name,
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
            Log::warning("[ExecutionTaskSynchronizer] No se pudo registrar AgentTask para task '{$taskId}': " . $e->getMessage());
        }
    }
}

