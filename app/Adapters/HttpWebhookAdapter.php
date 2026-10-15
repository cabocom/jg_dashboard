<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Adapters;

use App\Adapters\Contracts\InputNormalizerInterface;
use App\Adapters\Contracts\OutputFormatterInterface;
use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\AuditEvent;
use App\Services\Agents\AgentHttpClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class HttpWebhookAdapter
{
    public function __construct(
        protected InputNormalizerInterface $normalizer,
        protected OutputFormatterInterface $formatter
    ) {}

    /**
     * Process an external HTTP/Webhook stimulus into an agent execution and format the output.
     *
     * @param  Request  $request Incoming HTTP request
     * @param  string|null  $targetAgentId Optional target agent ID
     * @return array{http_code: int, headers: array, body: array}
     */
    public function handle(Request $request, ?string $targetAgentId = null): array
    {
        // 1. Authenticate webhook request if configured
        $authError = $this->verifyAuthentication($request);
        if ($authError !== null) {
            return $authError;
        }

        // 2. Normalize raw stimulus into canonical AgentInput contract
        $context = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $request->headers->all(),
            'agent_id' => $targetAgentId ?? $request->input('agent_id'),
            'format' => $request->input('format', $request->header('X-Response-Format', Config::get('adapters.default_format', 'generic'))),
        ];

        $agentInput = $this->normalizer->normalize($request, $context);

        $executionId = $agentInput['execution_id'];
        $threadId = $agentInput['thread_id'];
        $correlationId = $agentInput['correlation_id'];
        $explicitAgentId = $targetAgentId ?: $request->input('agent_id');

        // 3. Resolve target agent instance
        $agent = null;
        if (!empty($explicitAgentId)) {
            $agent = Agent::find($explicitAgentId);

            if (!$agent) {
                return [
                    'http_code' => 404,
                    'headers' => ['X-Correlation-ID' => $correlationId],
                    'body' => [
                        'error' => 'Agent Not Found',
                        'message' => "El agente con ID '{$explicitAgentId}' no existe.",
                        'correlation_id' => $correlationId,
                    ],
                ];
            }

            if (!$agent->isActive()) {
                return [
                    'http_code' => 400,
                    'headers' => ['X-Correlation-ID' => $correlationId],
                    'body' => [
                        'error' => 'Agent Unavailable',
                        'message' => "El agente '{$agent->name}' no está disponible (estado: {$agent->status}).",
                        'correlation_id' => $correlationId,
                    ],
                ];
            }

            $client = AgentHttpClient::forAgent($agent);
            $resolvedAgentId = $agent->agent_id;
        } else {
            $agent = Agent::active()->first();
            if ($agent) {
                $client = AgentHttpClient::forAgent($agent);
                $resolvedAgentId = $agent->agent_id;
            } else {
                $defaultId = Config::get('adapters.default_agent_id');
                $client = (new AgentHttpClient())->withAgentId($defaultId);
                $resolvedAgentId = $defaultId;
            }
        }

        $client->withCorrelationId($correlationId);

        // 4. Audit incoming stimulus
        AuditEvent::record(
            eventType: 'adapter.webhook.received',
            actionSummary: "Estímulo externo webhook recibido para ejecución {$executionId}",
            payload: [
                'execution_id' => $executionId,
                'thread_id' => $threadId,
                'correlation_id' => $correlationId,
                'agent_id' => $resolvedAgentId,
                'source' => $agentInput['metadata']->source ?? 'webhook',
                'prompt' => $agentInput['prompt'],
            ],
            agent: $resolvedAgentId,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $isAsync = !empty($agentInput['parameters']->async);

        // 5. Invoke target agent execution
        $response = $client->triggerExecution($agentInput);

        // 6. Handle agent gateway/upstream failure
        if (!$response['success']) {
            $httpCode = match ($response['status_code']) {
                503 => 503,
                504 => 504,
                default => 502,
            };

            // Register failed task
            try {
                AgentTask::updateOrCreate(
                    ['task_id' => $executionId],
                    [
                        'thread_id' => $threadId,
                        'status' => 'FAILED',
                        'payload_json' => $agentInput,
                        'result_json' => [
                            'error' => $response['error'] ?? 'Fallo de pasarela hacia jg_agente',
                            'upstream_status' => $response['status_code'],
                        ],
                        'created_at' => now(),
                        'started_at' => now(),
                        'completed_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning("[HttpWebhookAdapter] No se pudo persistir AgentTask fallida: " . $e->getMessage());
            }

            return [
                'http_code' => $httpCode,
                'headers' => ['X-Correlation-ID' => $correlationId],
                'body' => [
                    'error' => 'Agent Gateway Error',
                    'message' => $response['error'] ?? 'Error al comunicar con jg_agente',
                    'execution_id' => $executionId,
                    'thread_id' => $threadId,
                    'correlation_id' => $correlationId,
                ],
            ];
        }

        // 7. Success handling & Audit completion
        $responseData = $response['data'] ?? [];
        $requiresApproval = (bool) (
            ($responseData['requires_approval'] ?? false)
            || ($responseData['data']['requires_approval'] ?? false)
            || ($responseData['status'] ?? '') === 'waiting_approval'
            || ($responseData['data']['status'] ?? '') === 'waiting_approval'
        );

        $finalStatus = $requiresApproval ? 'WAITING_APPROVAL' : ($isAsync ? 'PENDING' : 'COMPLETED');

        // Persist completed / pending task
        try {
            AgentTask::updateOrCreate(
                ['task_id' => $executionId],
                [
                    'thread_id' => $threadId,
                    'status' => $finalStatus,
                    'payload_json' => $agentInput,
                    'result_json' => $responseData,
                    'created_at' => now(),
                    'started_at' => now(),
                    'completed_at' => ($isAsync || $requiresApproval) ? null : now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("[HttpWebhookAdapter] No se pudo persistir AgentTask: " . $e->getMessage());
        }

        AuditEvent::record(
            eventType: 'adapter.webhook.completed',
            actionSummary: "Ejecución webhook {$executionId} procesada ({$finalStatus})",
            payload: [
                'execution_id' => $executionId,
                'thread_id' => $threadId,
                'correlation_id' => $correlationId,
                'agent_id' => $resolvedAgentId,
                'status' => $finalStatus,
            ],
            agent: $resolvedAgentId,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        // 8. Format response according to requested client flavor
        $targetFormat = (string) ($agentInput['parameters']->format ?? $context['format'] ?? 'generic');
        $formattedBody = $this->formatter->format($response, $targetFormat, [
            'execution_id' => $executionId,
            'thread_id' => $threadId,
            'correlation_id' => $correlationId,
            'agent_id' => $resolvedAgentId,
            'status' => $finalStatus,
        ]);

        $httpStatusCode = ($isAsync || $requiresApproval) ? 202 : 200;

        return [
            'http_code' => $httpStatusCode,
            'headers' => ['X-Correlation-ID' => $correlationId],
            'body' => $formattedBody,
        ];
    }

    /**
     * Verify authentication of incoming webhook requests if configured.
     */
    protected function verifyAuthentication(Request $request): ?array
    {
        $configuredSecret = Config::get('adapters.webhook_secret');
        $requireAuth = Config::get('adapters.require_auth', false);

        // If no secret is configured and auth is not strictly required, allow request
        if (empty($configuredSecret) && !$requireAuth) {
            return null;
        }

        // Check X-Webhook-Secret header
        $providedSecret = $request->header('X-Webhook-Secret');

        // Check Authorization: Bearer <secret>
        if (empty($providedSecret)) {
            $bearer = $request->bearerToken();
            if (!empty($bearer)) {
                $providedSecret = $bearer;
            }
        }

        // Check query param ?token=
        if (empty($providedSecret)) {
            $providedSecret = $request->query('token');
        }

        if (empty($providedSecret) || !hash_equals((string) $configuredSecret, (string) $providedSecret)) {
            return [
                'http_code' => 401,
                'headers' => [],
                'body' => [
                    'error' => 'Unauthorized',
                    'message' => 'Credencial de webhook inválida o ausente (X-Webhook-Secret).',
                ],
            ];
        }

        return null;
    }
}
