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

use App\Actions\Hitl\ApproveHitlRequestAction;
use App\Actions\Hitl\ReconcileHitlApprovalsAction;
use App\Actions\Hitl\RegisterHitlApprovalAction;
use App\Actions\Hitl\RejectHitlRequestAction;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HitlApprovalService
{
    public function __construct(
        protected RegisterHitlApprovalAction $registerAction = new RegisterHitlApprovalAction,
        protected ApproveHitlRequestAction $approveAction = new ApproveHitlRequestAction,
        protected RejectHitlRequestAction $rejectAction = new RejectHitlRequestAction,
        protected ReconcileHitlApprovalsAction $reconcileAction = new ReconcileHitlApprovalsAction,
        protected HitlApprovalFormatter $formatter = new HitlApprovalFormatter,
        protected HitlTaskSynchronizer $taskSynchronizer = new HitlTaskSynchronizer
    ) {}

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
        return $this->registerAction->execute($data, $initiator, $ipAddress, $userAgent);
    }

    /**
     * Retrieve paginated pending approval requests.
     */
    public function getPendingApprovals(
        ?string $agentId = null,
        ?string $riskLevel = null,
        int $perPage = 50
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
        return $this->formatter->extractActionSummary($approval);
    }

    /**
     * Format an approval request into a standardized API payload.
     *
     * @return array<string, mixed>
     */
    public function formatApprovalItem(ApprovalRequest $approval): array
    {
        return $this->formatter->formatApprovalItem($approval);
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
        return $this->approveAction->execute($approvalId, $resolver, $justification, $ipAddress, $userAgent);
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
        return $this->rejectAction->execute($approvalId, $resolver, $justification, $ipAddress, $userAgent);
    }

    /**
     * Sincroniza el estado de la tarea AgentTask asociada tras una resolución HITL.
     */
    protected function syncAssociatedTask(
        ApprovalRequest $approval,
        string $targetStatus,
        ?string $reason = null,
        ?array $data = null
    ): void {
        $this->taskSynchronizer->syncAssociatedTask($approval, $targetStatus, $reason, $data);
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
        return $this->reconcileAction->execute($ttlSeconds);
    }
}
