<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Services\Agents\AgentHttpClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExecutionController extends Controller
{
    /**
     * Trigger or resume a cognitive agent execution via jg_agente.
     */
    public function trigger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required_without:user_prompt|nullable|string|min:1',
            'user_prompt' => 'required_without:prompt|nullable|string|min:1',
            'agent_id' => 'nullable|string',
            'thread_id' => 'nullable|string',
            'parameters' => 'nullable|array',
            'async' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $user = $request->user();
        $targetAgent = null;
        $targetAgentId = $validated['agent_id'] ?? null;

        // 1. Resolver el agente objetivo
        if (!empty($targetAgentId)) {
            $targetAgent = Agent::find($targetAgentId);

            if (!$targetAgent) {
                return response()->json([
                    'error' => 'Not Found',
                    'message' => "El agente con ID '{$targetAgentId}' no existe.",
                ], 404);
            }

            if (!$targetAgent->isActive()) {
                return response()->json([
                    'error' => 'Agent Unavailable',
                    'message' => "El agente '{$targetAgent->name}' no está disponible para ejecuciones (estado actual: {$targetAgent->status}).",
                ], 400);
            }

            $client = AgentHttpClient::forAgent($targetAgent);
        } else {
            // Si no se especifica agente, intentamos resolver el primer agente activo registrado
            $targetAgent = Agent::active()->first();

            if ($targetAgent) {
                $targetAgentId = $targetAgent->agent_id;
                $client = AgentHttpClient::forAgent($targetAgent);
            } else {
                $client = new AgentHttpClient();
            }
        }

        // 2. Normalizar payload de entrada hacia el contrato formal AgentInput
        $executionId = (string) Str::uuid();
        $threadId = !empty($validated['thread_id']) ? $validated['thread_id'] : (string) Str::uuid();
        $prompt = $validated['prompt'] ?? $validated['user_prompt'];
        $isAsync = $request->boolean('async');

        $parameters = (array) ($validated['parameters'] ?? []);
        if ($isAsync) {
            $parameters['async'] = true;
        }

        $agentInput = [
            'execution_id' => $executionId,
            'thread_id' => $threadId,
            'prompt' => $prompt,
            'user_prompt' => $prompt,
            'parameters' => (object) $parameters,
            'user_context' => [
                'user_id' => (string) $user->id,
                'username' => $user->name ?? $user->email,
                'email' => $user->email,
                'role' => $user->role,
                'permissions' => method_exists($user, 'getPermissions') ? $user->getPermissions() : [],
            ],
            'metadata' => (object) array_merge((array) ($validated['metadata'] ?? []), [
                'triggered_by' => $user->email,
                'user_role' => $user->role,
                'client_ip' => $request->ip(),
                'agent_id' => $targetAgentId,
            ]),
        ];

        // 3. Invocar al Execution Plane mediante AgentHttpClient
        $response = $client->triggerExecution($agentInput);

        // 4. Registrar evento de auditoría en el Dashboard
        AuditEvent::record(
            eventType: 'execution.triggered',
            actionSummary: "Ejecución iniciada: {$executionId} (thread: {$threadId})",
            payload: [
                'execution_id' => $executionId,
                'thread_id' => $threadId,
                'agent_id' => $targetAgentId,
                'async' => $isAsync,
                'success' => $response['success'],
                'status_code' => $response['status_code'],
            ],
            user: $user,
            agent: $targetAgentId,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        // 5. Manejo de error de comunicación o fallo en el Execution Plane
        if (!$response['success']) {
            $httpCode = match ($response['status_code']) {
                503 => 503,
                504 => 504,
                default => 502,
            };

            return response()->json([
                'error' => 'Agent Gateway Error',
                'message' => $response['error'] ?? 'Error al comunicar con jg_agente',
                'execution_id' => $executionId,
                'thread_id' => $threadId,
                'agent_id' => $targetAgentId,
                'upstream_status' => $response['status_code'],
            ], $httpCode);
        }

        $responseData = $response['data'] ?? [];
        $requiresApproval = ($responseData['requires_approval'] ?? false) === true ||
            ($responseData['status'] ?? '') === 'waiting_approval';

        // 6. Si la ejecución queda detenida en HITL, persistir solicitud mediante HitlApprovalService
        if ($requiresApproval) {
            $effectiveThreadId = $responseData['thread_id'] ?? $threadId;
            $effectiveTaskId = $responseData['execution_id'] ?? $executionId;

            app(\App\Services\Hitl\HitlApprovalService::class)->registerPendingApproval([
                'thread_id' => $effectiveThreadId,
                'agent_id' => $targetAgentId,
                'task_id' => $effectiveTaskId,
                'risk_level' => $responseData['risk_level'] ?? 'HIGH',
                'proposed_plan' => $responseData['plan'] ?? $responseData['proposed_plan'] ?? [],
                'compensation_commands' => $responseData['compensation_commands'] ?? [],
                'tools' => $responseData['tools'] ?? null,
            ], $user, $request->ip(), $request->userAgent());
        }

        $statusCode = ($isAsync || ($responseData['status'] ?? '') === 'in_progress') ? 202 : 200;
        $cognitiveSteps = $responseData['cognitive_steps']
            ?? $responseData['data']['cognitive_steps']
            ?? $responseData['data']['steps']
            ?? [];

        return response()->json([
            'execution_id' => $responseData['execution_id'] ?? $executionId,
            'thread_id' => $responseData['thread_id'] ?? $threadId,
            'agent_id' => $targetAgentId,
            'status' => $responseData['status'] ?? ($isAsync ? 'in_progress' : 'completed'),
            'requires_approval' => $requiresApproval,
            'pending_approval_id' => $responseData['pending_approval_id'] ?? null,
            'output' => $responseData['final_output'] ?? $responseData['output'] ?? null,
            'steps' => $cognitiveSteps,
            'cognitive_steps' => $cognitiveSteps,
            'data' => $responseData,
            'latency_ms' => $response['latency_ms'],
        ], $statusCode);
    }

    /**
     * Inspect execution state, step history and HITL checkpoints for a specific thread on a target agent.
     */
    public function show(Request $request, string $agentId, string $threadId): JsonResponse
    {
        $agent = Agent::find($agentId);

        if (!$agent) {
            return response()->json([
                'error' => 'Not Found',
                'message' => "El agente con ID '{$agentId}' no existe.",
            ], 404);
        }

        if ($agent->isPending()) {
            return response()->json([
                'error' => 'Agent Pending Registration',
                'message' => "El agente '{$agent->name}' aún se encuentra en estado PENDING y no ha completado su registro.",
            ], 400);
        }

        $client = AgentHttpClient::forAgent($agent);
        $response = $client->getExecutionStatus($threadId);

        if (!$response['success']) {
            if ($response['status_code'] === 404) {
                return response()->json([
                    'error' => 'Execution Not Found',
                    'message' => "No se encontró la ejecución con thread_id '{$threadId}' en el agente.",
                    'agent_id' => $agentId,
                    'thread_id' => $threadId,
                ], 404);
            }

            $httpCode = match ($response['status_code']) {
                503 => 503,
                504 => 504,
                default => 502,
            };

            return response()->json([
                'error' => 'Agent Gateway Error',
                'message' => $response['error'] ?? 'Error al consultar estado de la ejecución en jg_agente',
                'agent_id' => $agentId,
                'thread_id' => $threadId,
                'upstream_status' => $response['status_code'],
            ], $httpCode);
        }

        $data = $response['data'] ?? [];
        $localApproval = ApprovalRequest::where('thread_id', $threadId)->latest()->first();

        $unified = [
            'agent_id' => $agentId,
            'agent_name' => $agent->name,
            'thread_id' => $threadId,
            'execution_id' => $data['execution_id'] ?? null,
            'status' => $data['status'] ?? 'unknown',
            'prompt' => $data['prompt'] ?? null,
            'output' => $data['response'] ?? $data['final_output'] ?? $data['output'] ?? null,
            'current_step' => $data['current_step'] ?? 0,
            'next_nodes' => $data['next_nodes'] ?? [],
            'requires_approval' => $data['requires_approval'] ?? false,
            'approval_status' => $data['approval_status'] ?? ($localApproval?->status ?? null),
            'pending_approval_id' => $data['pending_approval_id'] ?? null,
            'plan' => $data['plan'] ?? null,
            'steps' => $data['steps'] ?? [],
            'cognitive_steps' => $data['cognitive_steps'] ?? $data['steps'] ?? [],
            'hitl_governance' => $localApproval ? [
                'approval_id' => $localApproval->id,
                'status' => $localApproval->status,
                'risk_level' => $localApproval->risk_level,
                'justification' => $localApproval->justification,
                'resolved_at' => $localApproval->resolved_at?->toIso8601String(),
            ] : null,
            'data' => $data,
            'latency_ms' => $response['latency_ms'],
        ];

        return response()->json($unified, 200);
    }
}
