<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Services\Hitl\HitlApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HitlApprovalController extends Controller
{
    protected HitlApprovalService $hitlService;

    public function __construct(HitlApprovalService $hitlService)
    {
        $this->hitlService = $hitlService;
    }

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

    /**
     * Approve a pending HITL request and resume execution on jg_agente.
     */
    public function approve(Request $request, string|int $approvalId): JsonResponse
    {
        $validated = $request->validate([
            'justification' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Autenticación requerida',
            ], 401);
        }

        $result = $this->hitlService->approveApproval(
            approvalId: $approvalId,
            resolver: $user,
            justification: $validated['justification'] ?? null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['success']) {
            return response()->json([
                'error' => 'ApprovalFailed',
                'message' => $result['error'] ?? 'No fue posible completar la aprobación',
            ], $result['status_code'] ?? 500);
        }

        return response()->json([
            'status' => 'approved',
            'message' => 'Solicitud HITL aprobada exitosamente y delegada al agente.',
            'data' => $result['data'] ?? [],
        ], 200);
    }

    /**
     * Reject a pending HITL request with mandatory justification and cancel execution on jg_agente.
     */
    public function reject(Request $request, string|int $approvalId): JsonResponse
    {
        $validated = $request->validate([
            'justification' => 'required|string|min:5|max:1000',
        ], [
            'justification.required' => 'El motivo o justificación del rechazo es obligatorio.',
            'justification.min' => 'El motivo de rechazo debe contener al menos 5 caracteres.',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Autenticación requerida',
            ], 401);
        }

        $result = $this->hitlService->rejectApproval(
            approvalId: $approvalId,
            resolver: $user,
            justification: $validated['justification'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['success']) {
            return response()->json([
                'error' => 'RejectionFailed',
                'message' => $result['error'] ?? 'No fue posible completar el rechazo',
            ], $result['status_code'] ?? 500);
        }

        return response()->json([
            'status' => 'rejected',
            'message' => 'Solicitud HITL rechazada exitosamente y cancelada en el agente.',
            'data' => $result['data'] ?? [],
        ], 200);
    }
}
