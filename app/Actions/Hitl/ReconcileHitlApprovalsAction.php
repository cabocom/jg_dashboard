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

use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\DB;

class ReconcileHitlApprovalsAction
{
    /**
     * Reconcile stuck approval requests in PROCESSING status that exceeded the TTL threshold.
     * Restores them atomically to PENDING with an immutable audit event trail.
     *
     * @return array{
     *     reconciled_count: int,
     *     reconciled_ids: array<int|string>
     * }
     */
    public function execute(int $ttlSeconds = 120): array
    {
        $threshold = now()->subSeconds($ttlSeconds);
        $reconciledIds = [];

        $staleApprovals = ApprovalRequest::where('status', 'PROCESSING')
            ->whereNotNull('processing_started_at')
            ->where('processing_started_at', '<=', $threshold)
            ->get();

        foreach ($staleApprovals as $approval) {
            DB::transaction(function () use ($approval, $ttlSeconds, &$reconciledIds) {
                /** @var ApprovalRequest|null $locked */
                $locked = ApprovalRequest::where('id', $approval->id)
                    ->lockForUpdate()
                    ->first();

                if (! $locked || $locked->status !== 'PROCESSING') {
                    return;
                }

                $startedAtIso = $locked->processing_started_at?->toIso8601String();

                $locked->update([
                    'status' => 'PENDING',
                    'processing_started_at' => null,
                ]);

                AuditEvent::record(
                    eventType: 'hitl.reconciled_stale_processing',
                    actionSummary: "Solicitud HITL {$locked->id} (hilo {$locked->thread_id}) restaurada a PENDING tras superar TTL de procesamiento ({$ttlSeconds}s)",
                    payload: [
                        'approval_id' => $locked->id,
                        'thread_id' => $locked->thread_id,
                        'agent_id' => $locked->agent_id,
                        'previous_status' => 'PROCESSING',
                        'new_status' => 'PENDING',
                        'processing_started_at' => $startedAtIso,
                        'ttl_seconds' => $ttlSeconds,
                        'reconciled_at' => now()->toIso8601String(),
                    ]
                );

                $reconciledIds[] = $locked->id;
            });
        }

        return [
            'reconciled_count' => count($reconciledIds),
            'reconciled_ids' => $reconciledIds,
        ];
    }
}

