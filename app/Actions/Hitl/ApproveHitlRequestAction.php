<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Actions\Hitl;

use App\Models\User;
use App\Services\Hitl\HitlApprovalFormatter;
use App\Services\Hitl\HitlTaskSynchronizer;
use App\Services\Hitl\ResolveHitlRequestService;

class ApproveHitlRequestAction
{
    protected ResolveHitlRequestService $resolverService;

    public function __construct(
        ?HitlApprovalFormatter $formatter = null,
        ?HitlTaskSynchronizer $taskSynchronizer = null,
        ?ResolveHitlRequestService $resolverService = null
    ) {
        $this->resolverService = $resolverService ?? new ResolveHitlRequestService(
            $formatter ?? new HitlApprovalFormatter,
            $taskSynchronizer ?? new HitlTaskSynchronizer
        );
    }

    /**
     * Approve a pending approval request, delegate command to jg_agente and record audit log.
     *
     * @return array{success: bool, status_code: int, data?: array, error?: string}
     */
    public function execute(
        int|string $approvalId,
        User $resolver,
        ?string $justification = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        return $this->resolverService->resolve(
            $approvalId,
            $resolver,
            'approve',
            $justification,
            $ipAddress,
            $userAgent
        );
    }
}
