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

use App\Models\AgentTask;
use App\Models\ApprovalRequest;
use Illuminate\Support\Facades\Log;

class HitlTaskSynchronizer
{
    /**
     * Sincroniza el estado de la tarea AgentTask asociada tras una resolución HITL.
     */
    public function syncAssociatedTask(
        ApprovalRequest $approval,
        string $targetStatus,
        ?string $reason = null,
        ?array $data = null
    ): void {
        try {
            // Reconciliar cualquier otra solicitud duplicada huérfana para el mismo thread_id
            if (! empty($approval->thread_id) && in_array($approval->status, ['APPROVED', 'REJECTED', 'CANCELLED'], true)) {
                ApprovalRequest::where('thread_id', $approval->thread_id)
                    ->where('id', '!=', $approval->id)
                    ->whereIn('status', ['PENDING', 'PROCESSING'])
                    ->update([
                        'status' => $approval->status,
                        'user_id' => $approval->user_id,
                        'resolved_at' => $approval->resolved_at ?? now(),
                        'justification' => $approval->justification,
                    ]);
            }

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

            if ($targetStatus === 'COMPLETED' || $targetStatus === 'FAILED') {
                $updates = [
                    'status' => $targetStatus,
                    'completed_at' => now(),
                ];
                if (! empty($approval->justification) && preg_match('/(Operador_[A-Za-z0-9_]+)/', $approval->justification, $matches)) {
                    $payloadJson = is_array($task->payload_json) ? $task->payload_json : [];
                    $baseUser = $payloadJson['username'] ?? ($task->user_name ?? 'Demo HITL');
                    if (! str_contains((string) $baseUser, $matches[1])) {
                        $payloadJson['username'] = "{$baseUser} ({$matches[1]})";
                        $updates['payload_json'] = $payloadJson;
                    }
                }
                if (! empty($data)) {
                    $res = is_array($task->result_json) ? $task->result_json : [];
                    $merged = array_merge($res, $data);
                    $finalText = $data['final_output'] ?? $data['output'] ?? $data['response'] ?? null;
                    if ($finalText) {
                        $merged['output'] = $finalText;
                        $merged['response'] = $finalText;
                        $merged['final_output'] = $finalText;
                    }
                    $freshSteps = $data['data']['cognitive_steps']
                        ?? $data['cognitive_steps']
                        ?? $data['data']['steps']
                        ?? $data['steps']
                        ?? null;
                    if (is_array($freshSteps) && ! empty($freshSteps)) {
                        $normalizedSteps = array_map(function ($step) {
                            if (is_array($step) && ($step['status'] ?? '') === 'waiting_approval') {
                                $step['status'] = 'completed';
                            }

                            return $step;
                        }, $freshSteps);
                        $merged['cognitive_steps'] = $normalizedSteps;
                        $merged['steps'] = $normalizedSteps;
                        if (isset($merged['data']) && is_array($merged['data'])) {
                            $merged['data']['cognitive_steps'] = $normalizedSteps;
                            $merged['data']['steps'] = $normalizedSteps;
                        }
                        $updates['trace_history_json'] = $normalizedSteps;
                    }
                    $updates['result_json'] = $merged;
                }
                $task->update($updates);
                Log::info("[HitlTaskSynchronizer] AgentTask '{$task->task_id}' sincronizada a {$targetStatus} tras resolución HITL");
            } elseif ($targetStatus === 'CANCELLED') {
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
                Log::info("[HitlTaskSynchronizer] AgentTask '{$task->task_id}' sincronizada a CANCELLED tras rechazo HITL");
            } elseif ($targetStatus === 'PROCESSING') {
                if ($task->status === 'WAITING_APPROVAL' || $task->status === 'QUEUED') {
                    $task->update(['status' => 'PROCESSING']);
                    Log::info("[HitlTaskSynchronizer] AgentTask '{$task->task_id}' reanudada a PROCESSING tras aprobación HITL");
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[HitlTaskSynchronizer] Error al sincronizar AgentTask para aprobación {$approval->id}: ".$e->getMessage());
        }
    }
}

