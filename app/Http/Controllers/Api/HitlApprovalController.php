<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Services\Hitl\HitlApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HitlApprovalController extends Controller
{
    public function __construct(
        protected HitlApprovalService $hitlService
    ) {}

    /**
     * List pending HITL approval requests awaiting human decision.
     */
    public function pending(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'nullable|string|max:64',
            'risk_level' => 'nullable|string|in:LOW,MEDIUM,HIGH,CRITICAL,low,medium,high,critical',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $agentId = $validated['agent_id'] ?? null;
        $riskLevel = !empty($validated['risk_level']) ? strtoupper($validated['risk_level']) : null;
        $perPage = (int) ($validated['per_page'] ?? 15);

        $paginator = $this->hitlService->getPendingApprovals(
            agentId: $agentId,
            riskLevel: $riskLevel,
            perPage: $perPage
        );

        $items = collect($paginator->items())->map(function (ApprovalRequest $approval) {
            return $this->hitlService->formatApprovalItem($approval);
        });

        // Calculate summary metrics for pending approvals
        $baseQuery = ApprovalRequest::pending();
        if (!empty($agentId)) {
            $baseQuery->where('agent_id', $agentId);
        }

        $summary = [
            'total_pending' => (clone $baseQuery)->count(),
            'critical_count' => (clone $baseQuery)->where('risk_level', 'CRITICAL')->count(),
            'high_count' => (clone $baseQuery)->where('risk_level', 'HIGH')->count(),
            'other_count' => (clone $baseQuery)->whereNotIn('risk_level', ['CRITICAL', 'HIGH'])->count(),
        ];

        return response()->json([
            'data' => $items,
            'summary' => $summary,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 200);
    }
}
