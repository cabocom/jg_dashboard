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

namespace App\Actions\Console;

use App\Models\Agent;
use App\Models\AgentTask;
use App\Services\Agents\AgentHttpClient;
use App\Services\Hitl\HitlApprovalService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ListConsoleExecutionsAction
{
    public function __construct(
        protected HitlApprovalService $hitlService = new HitlApprovalService(),
    ) {}

    public function execute(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', 50), 100);
        $agentMap = Agent::all()->keyBy('agent_id');

        $query = AgentTask::query();
        $this->applyDateFilters($query, $request->query('date_from'), $request->query('date_to'));
        $this->applyOrder($query, $request->query('sort_by', 'created_at'), $request->query('sort_order', 'desc'));
        $this->applyFilters(
            $query,
            $request->query('agent_id'),
            $request->query('status'),
            $request->query('agent_type'),
            $agentMap
        );

        $tasks = $query->limit($limit)->get();
        $this->syncPendingTasks($tasks, $agentMap);

        $items = $tasks->map(function (AgentTask $task) use ($agentMap) {
            return $this->formatTaskItem($task, $agentMap);
        });

        return response()->json([
            'executions' => $items,
            'total' => $items->count(),
        ], 200);
    }

    protected function applyDateFilters(Builder $query, ?string $dateFrom, ?string $dateTo): void
    {
        if ($dateFrom) {
            try {
                $normalizedFrom = preg_replace('/(\d{2}:\d{2}:\d{2})\s+(\d{2}:\d{2})$/', '$1+$2', trim($dateFrom));
                $query->where('created_at', '>=', Carbon::parse($normalizedFrom));
            } catch (\Throwable $e) {
                Log::debug("[ListConsoleExecutionsAction] Error al parsear dateFrom '{$dateFrom}': ".$e->getMessage());
            }
        }

        if ($dateTo) {
            try {
                $normalizedTo = preg_replace('/(\d{2}:\d{2}:\d{2})\s+(\d{2}:\d{2})$/', '$1+$2', trim($dateTo));
                $query->where('created_at', '<=', Carbon::parse($normalizedTo));
            } catch (\Throwable $e) {
                Log::debug("[ListConsoleExecutionsAction] Error al parsear dateTo '{$dateTo}': ".$e->getMessage());
            }
        }
    }

    protected function applyOrder(Builder $query, string $sortBy, string $sortOrder): void
    {
        $order = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
        if (in_array($sortBy, ['created_at', 'status'])) {
            $query->orderBy($sortBy, $order);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }

    protected function applyFilters(
        Builder $query,
        ?string $agentId,
        ?string $status,
        ?string $agentType,
        Collection $agentMap
    ): void {
        if ($agentId && $agentId !== 'ALL') {
            $query->where('payload_json->agent_id', $agentId);
        }

        if ($status && $status !== 'ALL') {
            $query->where('status', strtoupper($status));
        }

        if ($agentType && $agentType !== 'ALL') {
            $matchingAgentIds = $agentMap->filter(function (Agent $agent) use ($agentType) {
                return strcasecmp($agent->type, $agentType) === 0;
            })->keys()->all();

            $query->where(function ($q) use ($matchingAgentIds) {
                $q->whereIn('payload_json->agent_id', $matchingAgentIds);
            });
        }
    }

    protected function syncPendingTasks(Collection $tasks, Collection $agentMap): void
    {
        $pendingTasks = $tasks->filter(fn (AgentTask $t) => in_array($t->status, ['PROCESSING', 'QUEUED']));
        foreach ($pendingTasks as $pendingTask) {
            $this->syncSinglePendingTask($pendingTask, $agentMap);
        }
    }

    protected function syncSinglePendingTask(AgentTask $pendingTask, Collection $agentMap): void
    {
        try {
            $agent = $pendingTask->agent_id ? $agentMap->get($pendingTask->agent_id) : null;
            if (!$agent || $agent->isPending()) {
                return;
            }

            $client = AgentHttpClient::forAgent($agent);
            $threadId = $pendingTask->thread_id ?: $pendingTask->task_id;
            $statusRes = $client->getExecutionStatus($threadId);

            if (!$statusRes['success'] || empty($statusRes['data'])) {
                return;
            }

            $remoteData = $statusRes['data'];
            $remoteStatus = strtolower($remoteData['status'] ?? '');
            $syncedStatus = match ($remoteStatus) {
                'completed', 'success', 'resolved' => 'COMPLETED',
                'waiting_approval', 'awaiting_approval' => 'WAITING_APPROVAL',
                'failed', 'error' => 'FAILED',
                'cancelled', 'timeout' => 'CANCELLED',
                default => null,
            };

            if (!$syncedStatus || $syncedStatus === $pendingTask->status) {
                return;
            }

            $pendingTask->update([
                'status' => $syncedStatus,
                'completed_at' => !empty($remoteData['completed_at'])
                    ? Carbon::parse($remoteData['completed_at'])
                    : now(),
                'result_json' => array_merge(
                    is_array($pendingTask->result_json) ? $pendingTask->result_json : [],
                    $remoteData
                ),
            ]);
            $pendingTask->status = $syncedStatus;

            if ($syncedStatus === 'WAITING_APPROVAL') {
                $this->registerApprovalForWaitingTask($pendingTask, $threadId, $remoteData);
            }
        } catch (\Throwable $e) {
            Log::debug("[ListConsoleExecutionsAction] Sincronización pasiva de tarea fallida para thread '{$threadId}': ".$e->getMessage());
        }
    }

    protected function registerApprovalForWaitingTask(AgentTask $pendingTask, string $threadId, array $remoteData): void
    {
        try {
            $planData = $remoteData['data']['plan'] ?? ($remoteData['plan'] ?? ['prompt' => $pendingTask->prompt]);
            $cogSteps = $remoteData['cognitive_steps'] ?? ($remoteData['data']['cognitive_steps'] ?? ($remoteData['steps'] ?? []));
            $this->hitlService->registerPendingApproval([
                'thread_id' => $threadId,
                'agent_id' => $pendingTask->agent_id,
                'task_id' => $pendingTask->task_id,
                'prompt' => $pendingTask->prompt,
                'risk_level' => $remoteData['data']['risk_assessment']['risk_level'] ?? ($remoteData['risk_level'] ?? 'HIGH'),
                'proposed_plan' => $planData,
                'cognitive_steps' => $cogSteps,
                'compensation_commands' => $remoteData['data']['compensation_commands'] ?? ($remoteData['compensation_commands'] ?? []),
                'tools' => $remoteData['tools'] ?? null,
            ]);
        } catch (\Throwable $hitlEx) {
            Log::warning("[ListConsoleExecutionsAction] Error al registrar HITL durante sincronización en listExecutions: " . $hitlEx->getMessage());
        }
    }

    protected function formatTaskItem(AgentTask $task, Collection $agentMap): array
    {
        $prompt = $task->prompt;
        $agentId = $task->agent_id;
        $agent = $agentId ? $agentMap->get($agentId) : null;

        return [
            'task_id' => $task->task_id,
            'thread_id' => $task->thread_id,
            'agent_id' => $agentId,
            'agent_name' => $agent?->name ?? $agentId ?? 'Agente Desconocido',
            'agent_type' => $agent?->type ?? 'General',
            'user' => $task->user_name ?? 'Sistema',
            'status' => $task->status,
            'prompt' => $prompt,
            'prompt_truncated' => $prompt ? Str::limit($prompt, 80) : '(Sin prompt)',
            'created_at' => $task->created_at?->toIso8601String(),
            'created_at_formatted' => $task->created_at?->copy()->timezone(config('app.display_timezone', 'Europe/Madrid'))->format('d/m/Y H:i:s') ?? '-',
            'started_at' => $task->started_at?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'duration' => $task->duration,
        ];
    }
}

