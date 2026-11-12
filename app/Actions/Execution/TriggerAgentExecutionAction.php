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

namespace App\Actions\Execution;

use App\DTO\Execution\TriggerExecutionInput;
use App\DTO\Execution\TriggerExecutionResult;
use App\Models\Agent;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Agents\AgentHttpClient;
use App\Services\Execution\ExecutionTaskSynchronizer;
use App\Services\Hitl\HitlApprovalService;
use Illuminate\Support\Str;

class TriggerAgentExecutionAction
{
    public function __construct(
        protected HitlApprovalService $hitlService = new HitlApprovalService(),
        protected ExecutionTaskSynchronizer $taskSynchronizer = new ExecutionTaskSynchronizer(),
    ) {}

    public function execute(TriggerExecutionInput $input, ?User $user): TriggerExecutionResult
    {
        [$targetAgent, $client, $errResult] = $this->resolveAgentAndClient($input->agentId);
        if ($errResult !== null) {
            return $errResult;
        }

        $targetAgentId = $targetAgent?->agent_id;
        $executionId = (string) Str::uuid();
        $threadId = $input->threadId ?: (string) Str::uuid();
        $client->withCorrelationId($input->correlationId);

        $statusCallbackUrl = $this->buildStatusCallbackUrl($executionId);
        $agentInput = $this->buildAgentInput($input, $user, $targetAgentId, $executionId, $threadId, $statusCallbackUrl);

        $response = $client->triggerExecution($agentInput);
        $this->recordAuditEvent($input, $user, $targetAgentId, $executionId, $threadId, $response);

        if (!$response['success']) {
            return $this->handleUpstreamFailure($input, $user, $targetAgentId, $executionId, $threadId, $response);
        }

        return $this->processSuccessfulExecution($input, $user, $targetAgentId, $executionId, $threadId, $statusCallbackUrl, $response);
    }

    protected function resolveAgentAndClient(?string $agentId): array
    {
        if (!empty($agentId)) {
            $agent = Agent::find($agentId);
            if (!$agent) {
                return [null, null, new TriggerExecutionResult(404, [
                    'error' => 'Not Found',
                    'message' => "El agente con ID '{$agentId}' no existe.",
                ], '')];
            }
            if (!$agent->isActive()) {
                return [null, null, new TriggerExecutionResult(400, [
                    'error' => 'Agent Unavailable',
                    'message' => "El agente '{$agent->name}' no está disponible para ejecuciones (estado actual: {$agent->status}).",
                ], '')];
            }
            return [$agent, AgentHttpClient::forAgent($agent), null];
        }

        $agent = Agent::active()->first();
        $client = $agent ? AgentHttpClient::forAgent($agent) : new AgentHttpClient();
        return [$agent, $client, null];
    }

    protected function buildStatusCallbackUrl(string $executionId): string
    {
        $internalBaseUrl = env('DASHBOARD_INTERNAL_URL', config('services.dashboard.internal_url', 'http://jg_dashboard:8080'));
        return rtrim($internalBaseUrl, '/') . "/api/v1/internal/executions/{$executionId}/status-update";
    }

    protected function buildAgentInput(
        TriggerExecutionInput $input,
        ?User $user,
        ?string $agentId,
        string $executionId,
        string $threadId,
        string $callbackUrl
    ): array {
        $parameters = $input->parameters;
        if ($input->isAsync) {
            $parameters['async'] = true;
            $parameters['status_callback_url'] = $callbackUrl;
            $parameters['callback_url'] = $callbackUrl;
        }

        $displayUsername = $input->operatorHeader ?: ($user->name ?? $user->email ?? 'operator');

        return [
            'execution_id' => $executionId,
            'thread_id' => $threadId,
            'correlation_id' => $input->correlationId,
            'prompt' => $input->prompt,
            'user_prompt' => $input->prompt,
            'callback_url' => $input->isAsync ? $callbackUrl : null,
            'parameters' => (object) $parameters,
            'user_context' => [
                'user_id' => (string) ($user->id ?? ''),
                'username' => $displayUsername,
                'email' => $user->email ?? '',
                'role' => $user->role ?? 'operator',
                'permissions' => method_exists($user, 'getPermissions')
                    ? $user->getPermissions()
                    : (($user->role ?? '') === 'admin' ? ['*', 'admin'] : []),
            ],
            'metadata' => (object) array_merge($input->metadata, [
                'correlation_id' => $input->correlationId,
                'status_callback_url' => $input->isAsync ? $callbackUrl : null,
                'triggered_by' => $user->email ?? 'system',
                'operator_label' => $displayUsername,
                'user_role' => $user->role ?? 'operator',
                'client_ip' => $input->clientIp,
                'agent_id' => $agentId,
            ]),
        ];
    }

    protected function recordAuditEvent(
        TriggerExecutionInput $input,
        ?User $user,
        ?string $agentId,
        string $executionId,
        string $threadId,
        array $response
    ): void {
        AuditEvent::record(
            eventType: 'execution.triggered',
            actionSummary: "Ejecución iniciada: {$executionId} (thread: {$threadId})",
            payload: [
                'execution_id' => $executionId,
                'thread_id' => $threadId,
                'correlation_id' => $input->correlationId,
                'agent_id' => $agentId,
                'async' => $input->isAsync,
                'success' => $response['success'],
                'status_code' => $response['status_code'],
            ],
            user: $user,
            agent: $agentId,
            ipAddress: $input->clientIp,
            userAgent: $input->userAgent
        );
    }

    protected function handleUpstreamFailure(
        TriggerExecutionInput $input,
        ?User $user,
        ?string $agentId,
        string $executionId,
        string $threadId,
        array $response
    ): TriggerExecutionResult {
        $httpCode = match ($response['status_code']) {
            503 => 503,
            504 => 504,
            default => 502,
        };

        $this->taskSynchronizer->recordFailedTask($executionId, $threadId, $agentId, $input, $user, $response);

        return new TriggerExecutionResult($httpCode, [
            'error' => 'Agent Gateway Error',
            'message' => $response['error'] ?? 'Error al comunicar con jg_agente',
            'execution_id' => $executionId,
            'thread_id' => $threadId,
            'correlation_id' => $input->correlationId,
            'agent_id' => $agentId,
            'upstream_status' => $response['status_code'],
        ], $input->correlationId);
    }

    protected function processSuccessfulExecution(
        TriggerExecutionInput $input,
        ?User $user,
        ?string $agentId,
        string $executionId,
        string $threadId,
        string $callbackUrl,
        array $response
    ): TriggerExecutionResult {
        $responseData = $response['data'] ?? [];
        $cognitiveSteps = $responseData['cognitive_steps']
            ?? $responseData['data']['cognitive_steps']
            ?? $responseData['data']['steps']
            ?? [];

        $planData = $responseData['data']['plan'] ?? $responseData['plan'] ?? [];
        if (is_array($planData)) {
            $planData['prompt'] = $input->prompt;
            $planData['cognitive_steps'] = $cognitiveSteps;
        }

        $requiresApproval = (bool) (
            ($responseData['requires_approval'] ?? false)
            || ($responseData['data']['requires_approval'] ?? false)
            || ($responseData['status'] ?? '') === 'waiting_approval'
            || ($responseData['data']['status'] ?? '') === 'waiting_approval'
            || !empty($responseData['pending_approval_id'])
            || !empty($responseData['data']['pending_approval_id'])
        );

        $effectiveThreadId = $responseData['thread_id'] ?? $threadId;
        $effectiveTaskId = $responseData['execution_id'] ?? $executionId;

        if ($requiresApproval) {
            $this->hitlService->registerPendingApproval([
                'thread_id' => $effectiveThreadId,
                'agent_id' => $agentId,
                'task_id' => $effectiveTaskId,
                'prompt' => $input->prompt,
                'risk_level' => $responseData['data']['risk_assessment']['risk_level'] ?? $responseData['risk_level'] ?? 'HIGH',
                'proposed_plan' => $planData,
                'cognitive_steps' => $cognitiveSteps,
                'compensation_commands' => $responseData['data']['compensation_commands'] ?? $responseData['compensation_commands'] ?? [],
                'tools' => $responseData['tools'] ?? null,
            ], $user, $input->clientIp, $input->userAgent);
        }

        $this->taskSynchronizer->recordExecutionTask(
            $input,
            $user,
            $agentId,
            $effectiveTaskId,
            $effectiveThreadId,
            $responseData,
            $requiresApproval
        );

        $statusCode = ($input->isAsync || ($responseData['status'] ?? '') === 'in_progress') ? 202 : 200;
        $effectiveCorrelationId = $responseData['correlation_id'] ?? $input->correlationId;

        return new TriggerExecutionResult($statusCode, [
            'execution_id' => $effectiveTaskId,
            'thread_id' => $effectiveThreadId,
            'correlation_id' => $effectiveCorrelationId,
            'agent_id' => $agentId,
            'status' => $responseData['status'] ?? ($input->isAsync ? 'in_progress' : 'completed'),
            'status_callback_url' => $input->isAsync ? $callbackUrl : null,
            'requires_approval' => $requiresApproval,
            'pending_approval_id' => $responseData['pending_approval_id'] ?? null,
            'output' => $responseData['final_output'] ?? $responseData['output'] ?? $responseData['response'] ?? null,
            'steps' => $cognitiveSteps,
            'cognitive_steps' => $cognitiveSteps,
            'data' => $responseData,
            'latency_ms' => $response['latency_ms'],
        ], $effectiveCorrelationId);
    }
}
