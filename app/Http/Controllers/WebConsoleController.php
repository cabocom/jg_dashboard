<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Services\Agents\AgentRegistrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebConsoleController extends Controller
{
    /**
     * Render the high-tech interactive web console for operators and admins.
     */
    public function index(Request $request): View
    {
        $totalAgents = Agent::count();
        $activeAgents = Agent::where('status', 'ACTIVE')->count();
        $offlineAgents = Agent::where('status', 'OFFLINE')->count();
        $pendingApprovalsCount = ApprovalRequest::pending()->count();

        return view('console.index', [
            'totalAgents' => $totalAgents,
            'activeAgents' => $activeAgents,
            'offlineAgents' => $offlineAgents,
            'pendingApprovalsCount' => $pendingApprovalsCount,
        ]);
    }

    /**
     * List all registered agents with real-time status and summary KPIs for the web console.
     */
    public function listAgents(Request $request): JsonResponse
    {
        $agents = Agent::orderBy('created_at', 'desc')->get();
        $total = $agents->count();
        $active = $agents->where('status', 'ACTIVE')->count();
        $offline = $agents->where('status', 'OFFLINE')->count();

        $items = $agents->map(function (Agent $agent) {
            $metrics = $agent->metrics_json ?? [];
            $capabilities = $agent->capabilities_json ?? [];
            return [
                'agent_id' => $agent->agent_id,
                'name' => $agent->name,
                'status' => $agent->status,
                'version' => $agent->version,
                'is_healthy' => $agent->status === 'ACTIVE' || $agent->isHealthy(90),
                'last_heartbeat_at' => $agent->last_heartbeat_at?->toIso8601String(),
                'capabilities' => $capabilities,
                'capabilities_json' => $capabilities,
                'metrics' => $metrics,
                'metrics_json' => $metrics,
                'metadata' => $agent->metadata_json ?? [],
                'created_at' => $agent->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $items,
            'summary' => [
                'total' => $total,
                'active' => $active,
                'offline' => $offline,
            ],
        ]);
    }

    /**
     * Fetch the live capabilities for an agent for the web console.
     */
    public function agentCapabilities(string $agentId, \App\Http\Controllers\Api\AgentController $agentController): JsonResponse
    {
        return $agentController->capabilities($agentId);
    }

    /**
     * Provision a new agent from the web console and return its one-time bootstrap token.
     */
    public function storeAgent(Request $request, AgentRegistrationService $service): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:128',
            'version' => 'nullable|string|max:32',
            'ttl_hours' => 'nullable|integer|min:1|max:168',
        ]);

        $result = $service->createAgent(
            name: $validated['name'],
            version: $validated['version'] ?? '1.0.0',
            ttlHours: (int) ($validated['ttl_hours'] ?? 24),
            adminUser: $request->user()
        );

        return response()->json([
            'agent_id' => $result['agent_id'],
            'name' => $result['name'],
            'status' => $result['status'],
            'bootstrap_token' => $result['bootstrap_token'],
            'expires_at' => $result['expires_at'],
            'ttl_hours' => $result['ttl_hours'],
            'created_at' => $result['agent']->created_at?->toISOString(),
        ], 201);
    }

    /**
     * List pending HITL approvals for the web console with summary and step details.
     */
    public function pendingApprovals(Request $request, \App\Services\Hitl\HitlApprovalService $service): JsonResponse
    {
        $agentId = $request->query('agent_id');
        $riskLevel = $request->query('risk_level');
        if ($riskLevel && strtoupper($riskLevel) !== 'ALL') {
            $riskLevel = strtoupper($riskLevel);
        } else {
            $riskLevel = null;
        }

        $paginator = $service->getPendingApprovals(
            agentId: !empty($agentId) ? $agentId : null,
            riskLevel: $riskLevel,
            perPage: 50
        );

        $items = collect($paginator->items())->map(function (ApprovalRequest $approval) use ($service) {
            return $service->formatApprovalItem($approval);
        });

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
                'total' => $paginator->total(),
            ],
        ], 200);
    }

    /**
     * Approve a pending HITL request directly from the web console.
     */
    public function approveApproval(Request $request, string|int $id, \App\Services\Hitl\HitlApprovalService $service): JsonResponse
    {
        $validated = $request->validate([
            'justification' => 'nullable|string|max:1000',
        ]);

        $user = $request->user()
            ?? \App\Models\User::whereIn('role', ['operator', 'admin'])->first()
            ?? \App\Models\User::first();

        if (!$user) {
            $user = \App\Models\User::create([
                'name' => 'Web Console Operator',
                'email' => 'operator.console@example.com',
                'password' => bcrypt(\Illuminate\Support\Str::random(16)),
                'role' => 'operator',
            ]);
        }

        $result = $service->approveApproval(
            approvalId: $id,
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
     * Reject a pending HITL request directly from the web console with mandatory justification.
     */
    public function rejectApproval(Request $request, string|int $id, \App\Services\Hitl\HitlApprovalService $service): JsonResponse
    {
        $validated = $request->validate([
            'justification' => 'required|string|min:5|max:1000',
        ], [
            'justification.required' => 'El motivo o justificación del rechazo es obligatorio.',
            'justification.min' => 'El motivo de rechazo debe contener al menos 5 caracteres.',
        ]);

        $user = $request->user()
            ?? \App\Models\User::whereIn('role', ['operator', 'admin'])->first()
            ?? \App\Models\User::first();

        if (!$user) {
            $user = \App\Models\User::create([
                'name' => 'Web Console Operator',
                'email' => 'operator.console@example.com',
                'password' => bcrypt(\Illuminate\Support\Str::random(16)),
                'role' => 'operator',
            ]);
        }

        $result = $service->rejectApproval(
            approvalId: $id,
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

    /**
     * Trigger an execution directly from the web console.
     */
    public function triggerExecution(Request $request, \App\Http\Controllers\Api\ExecutionController $controller): JsonResponse
    {
        if (!$request->user()) {
            $operator = \App\Models\User::whereIn('role', ['operator', 'admin'])->first() ?? \App\Models\User::first();
            if ($operator) {
                $request->setUserResolver(fn () => $operator);
            }
        }

        return $controller->trigger($request);
    }

    /**
     * Fetch execution status and step history directly for the web console.
     */
    public function showExecution(Request $request, string $agentId, string $threadId, \App\Http\Controllers\Api\ExecutionController $controller): JsonResponse
    {
        return $controller->show($request, $agentId, $threadId);
    }
}

