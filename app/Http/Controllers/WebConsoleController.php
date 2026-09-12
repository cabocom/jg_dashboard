<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\ApprovalRequest;
use App\Services\Agents\AgentRegistrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebConsoleController extends Controller
{
    /**
     * Render the high-tech interactive web console for operators and admins.
     */
    public function index(Request $request): View
    {
        $agents = Agent::all();
        $totalAgents = $agents->count();
        $activeAgents = $agents->filter(fn (Agent $a) => $a->status === 'ACTIVE' && $a->isHealthy(90))->count();
        $offlineAgents = $agents->filter(fn (Agent $a) => $a->status === 'OFFLINE' || ($a->status === 'ACTIVE' && !$a->isHealthy(90)))->count();
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

        $items = $agents->map(function (Agent $agent) {
            $metrics = $agent->metrics_json ?? [];
            $capabilities = $agent->capabilities_json ?? [];
            $isHealthy = $agent->status === 'ACTIVE' && $agent->isHealthy(90);
            $effectiveStatus = ($agent->status === 'ACTIVE' && !$isHealthy) ? 'OFFLINE' : $agent->status;

            return [
                'agent_id' => $agent->agent_id,
                'name' => $agent->name,
                'status' => $effectiveStatus,
                'version' => $agent->version,
                'is_healthy' => $isHealthy,
                'last_heartbeat_at' => $agent->last_heartbeat_at?->toIso8601String(),
                'capabilities' => $capabilities,
                'capabilities_json' => $capabilities,
                'metrics' => $metrics,
                'metrics_json' => $metrics,
                'metadata' => $agent->metadata_json ?? [],
                'created_at' => $agent->created_at?->toIso8601String(),
            ];
        });

        $total = $items->count();
        $active = $items->where('status', 'ACTIVE')->count();
        $offline = $items->where('status', 'OFFLINE')->count();

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
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Autenticación requerida para aprovisionar agentes.',
            ], 401);
        }

        if (!$user->hasRole('admin')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Acceso denegado: se requiere rol de administrador para aprovisionar agentes.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:128',
            'version' => 'nullable|string|max:32',
            'ttl_hours' => 'nullable|integer|min:1|max:168',
        ]);

        $result = $service->createAgent(
            name: $validated['name'],
            version: $validated['version'] ?? '1.0.0',
            ttlHours: (int) ($validated['ttl_hours'] ?? 24),
            adminUser: $user
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
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Autenticación requerida para resolver solicitudes HITL.',
            ], 401);
        }

        if (!$user->hasRole(['admin', 'operator'])) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Acceso denegado: rol insuficiente para resolver solicitudes HITL.',
            ], 403);
        }

        $validated = $request->validate([
            'justification' => 'nullable|string|max:1000',
        ]);

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
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Autenticación requerida para resolver solicitudes HITL.',
            ], 401);
        }

        if (!$user->hasRole(['admin', 'operator'])) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Acceso denegado: rol insuficiente para resolver solicitudes HITL.',
            ], 403);
        }

        $validated = $request->validate([
            'justification' => 'required|string|min:5|max:1000',
        ], [
            'justification.required' => 'El motivo o justificación del rechazo es obligatorio.',
            'justification.min' => 'El motivo de rechazo debe contener al menos 5 caracteres.',
        ]);

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
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Autenticación requerida para disparar ejecuciones.',
            ], 401);
        }

        if (!$user->hasRole(['admin', 'operator'])) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Acceso denegado: rol insuficiente para disparar ejecuciones.',
            ], 403);
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

    /**
     * List recent executions for the web console with truncated prompt, date, and status.
     */
    public function listExecutions(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', 20), 50);

        $tasks = AgentTask::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $agentMap = Agent::all()->keyBy('agent_id');

        // Sincronizar proactivamente ejecuciones que sigan en estado PROCESSING o QUEUED
        $pendingTasks = $tasks->filter(fn (AgentTask $t) => in_array($t->status, ['PROCESSING', 'QUEUED']));
        foreach ($pendingTasks as $pendingTask) {
            try {
                $agent = $pendingTask->agent_id ? $agentMap->get($pendingTask->agent_id) : null;
                if ($agent && !$agent->isPending()) {
                    $client = \App\Services\Agents\AgentHttpClient::forAgent($agent);
                    $threadId = $pendingTask->thread_id ?: $pendingTask->task_id;
                    $statusRes = $client->getExecutionStatus($threadId);
                    if ($statusRes['success'] && !empty($statusRes['data'])) {
                        $remoteData = $statusRes['data'];
                        $remoteStatus = strtolower($remoteData['status'] ?? '');
                        $syncedStatus = match ($remoteStatus) {
                            'completed', 'success', 'resolved' => 'COMPLETED',
                            'waiting_approval', 'awaiting_approval' => 'WAITING_APPROVAL',
                            'failed', 'error' => 'FAILED',
                            'cancelled', 'timeout' => 'CANCELLED',
                            default => null,
                        };
                        if ($syncedStatus && $syncedStatus !== $pendingTask->status) {
                            $pendingTask->update([
                                'status' => $syncedStatus,
                                'completed_at' => !empty($remoteData['completed_at'])
                                    ? \Carbon\Carbon::parse($remoteData['completed_at'])
                                    : now(),
                                'result_json' => array_merge(
                                    is_array($pendingTask->result_json) ? $pendingTask->result_json : [],
                                    $remoteData
                                ),
                            ]);
                            $pendingTask->status = $syncedStatus;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Silencioso para garantizar resiliencia en listados
            }
        }

        $items = $tasks->map(function (AgentTask $task) use ($agentMap) {
            $prompt = $task->prompt;
            $promptTruncated = $prompt ? Str::limit($prompt, 80) : '(Sin prompt)';
            $agentId = $task->agent_id;
            $agent = $agentId ? $agentMap->get($agentId) : null;
            $agentName = $agent?->name ?? $agentId ?? 'Agente Desconocido';

            return [
                'task_id' => $task->task_id,
                'thread_id' => $task->thread_id,
                'agent_id' => $agentId,
                'agent_name' => $agentName,
                'status' => $task->status,
                'prompt' => $prompt,
                'prompt_truncated' => $promptTruncated,
                'created_at' => $task->created_at?->toIso8601String(),
                'created_at_formatted' => $task->created_at?->format('d/m/Y H:i:s') ?? '-',
                'started_at' => $task->started_at?->toIso8601String(),
                'completed_at' => $task->completed_at?->toIso8601String(),
                'duration' => $task->duration,
            ];
        });

        return response()->json([
            'executions' => $items,
            'total' => $items->count(),
        ], 200);
    }
}

