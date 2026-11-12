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

use App\Models\AgentTask;
use App\Models\ApprovalRequest;
use App\Services\Hitl\HitlApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GetConsolePendingApprovalsAction
{
    public function __construct(
        protected HitlApprovalService $service = new HitlApprovalService(),
    ) {}

    public function execute(?string $agentId, ?string $riskLevel): JsonResponse
    {
        $normalizedRiskLevel = ($riskLevel && strtoupper($riskLevel) !== 'ALL')
            ? strtoupper($riskLevel)
            : null;

        $this->reconcileWaitingTasks();

        $paginator = $this->service->getPendingApprovals(
            agentId: !empty($agentId) ? $agentId : null,
            riskLevel: $normalizedRiskLevel,
            perPage: 50
        );

        $items = collect($paginator->items())->map(function (ApprovalRequest $approval) {
            return $this->service->formatApprovalItem($approval);
        });

        $summary = $this->computeSummary($agentId);

        return response()->json([
            'data' => $items,
            'summary' => $summary,
            'meta' => [
                'total' => $paginator->total(),
            ],
        ], 200);
    }

    protected function reconcileWaitingTasks(): void
    {
        try {
            $orphanWaitingTasks = AgentTask::where('status', 'WAITING_APPROVAL')
                ->whereNotNull('thread_id')
                ->get();

            foreach ($orphanWaitingTasks as $waitingTask) {
                $existingApproval = ApprovalRequest::where(function ($q) use ($waitingTask) {
                    $q->where('thread_id', $waitingTask->thread_id);
                    if (!empty($waitingTask->task_id)) {
                        $q->orWhere('task_id', $waitingTask->task_id);
                    }
                })->latest()->first();

                if ($existingApproval) {
                    if (in_array($existingApproval->status, ['REJECTED', 'CANCELLED'])) {
                        $waitingTask->update([
                            'status' => 'CANCELLED',
                            'completed_at' => $existingApproval->resolved_at ?? now(),
                        ]);
                        continue;
                    }
                    if (in_array($existingApproval->status, ['APPROVED', 'PROCESSING'])) {
                        $waitingTask->update(['status' => 'PROCESSING']);
                        continue;
                    }
                    if ($existingApproval->isPending()) {
                        continue;
                    }
                }

                $resultData = is_array($waitingTask->result_json) ? $waitingTask->result_json : [];
                $payloadData = is_array($waitingTask->payload_json) ? $waitingTask->payload_json : [];
                $agentIdCandidate = $waitingTask->agent_id ?? ($payloadData['agent_id'] ?? null);

                $this->service->registerPendingApproval([
                    'thread_id' => $waitingTask->thread_id,
                    'agent_id' => $agentIdCandidate,
                    'task_id' => $waitingTask->task_id,
                    'prompt' => $waitingTask->prompt,
                    'risk_level' => $resultData['risk_assessment']['risk_level'] ?? ($resultData['risk_level'] ?? 'HIGH'),
                    'proposed_plan' => $resultData['plan'] ?? ['prompt' => $waitingTask->prompt],
                    'cognitive_steps' => $resultData['cognitive_steps'] ?? ($resultData['steps'] ?? []),
                    'compensation_commands' => $resultData['compensation_commands'] ?? [],
                    'tools' => $resultData['tools'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("[GetConsolePendingApprovalsAction] Error en reconciliación HITL: " . $e->getMessage());
        }
    }

    protected function computeSummary(?string $agentId): array
    {
        $baseQuery = ApprovalRequest::pending();
        if (!empty($agentId)) {
            $baseQuery->where('agent_id', $agentId);
        }

        return [
            'total_pending' => (clone $baseQuery)->count(),
            'critical_count' => (clone $baseQuery)->where('risk_level', 'CRITICAL')->count(),
            'high_count' => (clone $baseQuery)->where('risk_level', 'HIGH')->count(),
            'other_count' => (clone $baseQuery)->whereNotIn('risk_level', ['CRITICAL', 'HIGH'])->count(),
        ];
    }
}

