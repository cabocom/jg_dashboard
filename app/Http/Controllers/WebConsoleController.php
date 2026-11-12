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

namespace App\Http\Controllers;

use App\Actions\Console\GetConsolePendingApprovalsAction;
use App\Actions\Console\ListConsoleAgentsAction;
use App\Actions\Console\ListConsoleExecutionsAction;
use App\Actions\Console\UpdateConsoleProfileAction;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\ExecutionController;
use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Services\Agents\AgentRegistrationService;
use App\Services\Hitl\HitlApprovalService;
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
    public function listAgents(ListConsoleAgentsAction $action): JsonResponse
    {
        return $action->execute();
    }

    /**
     * Fetch the live capabilities for an agent for the web console.
     */
    public function agentCapabilities(string $agentId, AgentController $agentController): JsonResponse
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
    public function pendingApprovals(Request $request, GetConsolePendingApprovalsAction $action): JsonResponse
    {
        return $action->execute(
            agentId: $request->query('agent_id'),
            riskLevel: $request->query('risk_level')
        );
    }

    /**
     * Approve a pending HITL request directly from the web console.
     */
    public function approveApproval(Request $request, string|int $id, HitlApprovalService $service): JsonResponse
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
    public function rejectApproval(Request $request, string|int $id, HitlApprovalService $service): JsonResponse
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
    public function triggerExecution(Request $request, ExecutionController $controller): JsonResponse
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

        return $controller->trigger($request, app(\App\Actions\Execution\TriggerAgentExecutionAction::class));
    }

    /**
     * Fetch execution status and step history directly for the web console.
     */
    public function showExecution(Request $request, string $agentId, string $threadId, ExecutionController $controller): JsonResponse
    {
        return $controller->show($request, $agentId, $threadId, app(\App\Actions\Execution\FetchExecutionTraceAction::class));
    }

    /**
     * List recent executions for the web console with truncated prompt, date, agent type, and status.
     */
    public function listExecutions(Request $request, ListConsoleExecutionsAction $action): JsonResponse
    {
        return $action->execute($request);
    }

    /**
     * Get current authenticated user profile for the web console.
     */
    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated', 'message' => 'No autenticado.'], 401);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
        ], 200);
    }

    /**
     * Update current authenticated user profile and password.
     */
    public function updateProfile(Request $request, UpdateConsoleProfileAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated', 'message' => 'No autenticado.'], 401);
        }

        return $action->execute($request, $user);
    }
}
