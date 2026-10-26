<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Adapters\Conversational;

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\AuditEvent;
use App\Services\Agents\AgentHttpClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MockAsteriskChatAdapter
{
    /**
     * Process a simulated conversational stimulus (Asterisk Voice Call or Web/Mobile Chat).
     *
     * @param  Request|array  $stimulus
     * @param  string|null  $targetAgentId
     * @return array{http_code: int, headers: array, body: array}
     */
    public function handle(Request|array $stimulus, ?string $targetAgentId = null): array
    {
        $payload = $stimulus instanceof Request ? $stimulus->all() : $stimulus;
        $headers = $stimulus instanceof Request ? $stimulus->headers->all() : [];
        $clientIp = $stimulus instanceof Request ? $stimulus->ip() : '127.0.0.1';
        $userAgent = $stimulus instanceof Request ? $stimulus->userAgent() : 'MockAsteriskAdapter/1.0';

        // 1. Detect simulation type (Asterisk Telephony vs Chat)
        $isAsterisk = $this->isAsteriskPayload($payload);
        $simulationType = $isAsterisk ? 'asterisk_voice' : 'chat';

        // 2. Extract conversational fields
        $prompt = $this->extractConversationalPrompt($payload, $isAsterisk);
        $callId = $payload['call_id'] ?? null;
        $chatId = $payload['chat_id'] ?? $payload['conversation_id'] ?? null;
        $threadId = (string) ($callId ?? $chatId ?? ($payload['thread_id'] ?? Str::uuid()));
        $callerId = $payload['caller_id'] ?? $payload['from'] ?? $payload['phone'] ?? ($isAsterisk ? 'SIP/1001' : 'user');
        $callerName = $payload['caller_name'] ?? $payload['caller_id'] ?? $payload['from'] ?? $payload['phone'] ?? $payload['sender'] ?? 'Llamante';
        $channel = $payload['channel'] ?? ($isAsterisk ? 'SIP/trunk-simulated-01' : 'web_chat');

        $executionId = !empty($payload['execution_id']) ? (string) $payload['execution_id'] : (string) Str::uuid();
        $correlationId = !empty($headers['x-correlation-id'][0])
            ? (string) $headers['x-correlation-id'][0]
            : (!empty($payload['correlation_id']) ? (string) $payload['correlation_id'] : (string) Str::uuid());

        // 3. Resolve conversational agent
        $requestedAgentId = $targetAgentId ?: ($payload['agent_id'] ?? null);
        $agent = $this->resolveConversationalAgent($requestedAgentId);

        if (!$agent && !empty($requestedAgentId)) {
            return [
                'http_code' => 404,
                'headers' => ['X-Correlation-ID' => $correlationId],
                'body' => [
                    'error' => 'Agent Not Found',
                    'message' => "El agente con ID '{$requestedAgentId}' no existe.",
                    'correlation_id' => $correlationId,
                ],
            ];
        }

        if ($agent && !$agent->isActive()) {
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

        $resolvedAgentId = $agent ? $agent->agent_id : Config::get('agent.default_agent_id');
        $client = $agent ? AgentHttpClient::forAgent($agent) : (new AgentHttpClient())->withAgentId($resolvedAgentId);
        $client->withCorrelationId($correlationId);

        // 4. Build canonical AgentInput
        $agentInput = [
            'execution_id' => $executionId,
            'thread_id' => $threadId,
            'correlation_id' => $correlationId,
            'agent_id' => $resolvedAgentId,
            'prompt' => $prompt,
            'user_prompt' => $prompt,
            'parameters' => (object) array_merge(
                (array) ($payload['parameters'] ?? []),
                [
                    'simulation_type' => $simulationType,
                    'channel' => $channel,
                    'caller_id' => $callerId,
                    'is_telephony' => $isAsterisk,
                ]
            ),
            'user_context' => [
                'user_id' => (string) $callerId,
                'username' => (string) $callerName,
                'email' => "{$callerId}@telephony.local",
                'role' => 'caller',
                'permissions' => ['agent:execute'],
            ],
            'metadata' => (object) [
                'agent_id' => $resolvedAgentId,
                'adapter' => 'mock_conversational',
                'simulation_type' => $simulationType,
                'channel' => $channel,
                'call_id' => $callId,
                'chat_id' => $chatId,
                'caller_id' => $callerId,
                'correlation_id' => $correlationId,
                'client_ip' => $clientIp,
                'user_agent' => $userAgent,
                'received_at' => now()->toIso8601String(),
            ],
        ];

        // 5. Audit incoming stimulus
        AuditEvent::record(
            eventType: 'adapter.mock_conversational.received',
            actionSummary: "Estímulo conversacional simulado ({$simulationType}) recibido para llamada/hilo {$threadId}",
            payload: [
                'execution_id' => $executionId,
                'thread_id' => $threadId,
                'correlation_id' => $correlationId,
                'agent_id' => $resolvedAgentId,
                'simulation_type' => $simulationType,
                'prompt' => $prompt,
            ],
            agent: $resolvedAgentId,
            ipAddress: $clientIp,
            userAgent: $userAgent
        );

        $startTime = microtime(true);

        // 6. Invoke execution upstream
        $response = $client->triggerExecution($agentInput);
        $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

        // 7. Handle upstream failure
        if (!$response['success']) {
            $httpCode = match ($response['status_code']) {
                503 => 503,
                504 => 504,
                default => 502,
            };

            try {
                AgentTask::updateOrCreate(
                    ['task_id' => $executionId],
                    [
                        'thread_id' => $threadId,
                        'agent_id' => $resolvedAgentId,
                        'status' => 'FAILED',
                        'payload_json' => array_merge((array) $agentInput, ['agent_id' => $resolvedAgentId]),
                        'result_json' => [
                            'error' => $response['error'] ?? 'Fallo al comunicar con agente conversacional',
                            'upstream_status' => $response['status_code'],
                        ],
                        'created_at' => now(),
                        'started_at' => now(),
                        'completed_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning("[MockAsteriskChatAdapter] No se pudo persistir AgentTask fallida: " . $e->getMessage());
            }

            return [
                'http_code' => $httpCode,
                'headers' => ['X-Correlation-ID' => $correlationId],
                'body' => [
                    'error' => 'Agent Gateway Error',
                    'message' => $response['error'] ?? 'Error al comunicar con agente conversacional',
                    'execution_id' => $executionId,
                    'thread_id' => $threadId,
                    'correlation_id' => $correlationId,
                ],
            ];
        }

        $responseData = $response['data'] ?? [];
        $outputText = $this->extractOutputText($responseData);

        // 8. Persist completed task
        try {
            AgentTask::updateOrCreate(
                ['task_id' => $executionId],
                [
                    'thread_id' => $threadId,
                    'agent_id' => $resolvedAgentId,
                    'status' => 'COMPLETED',
                    'payload_json' => array_merge((array) $agentInput, ['agent_id' => $resolvedAgentId]),
                    'result_json' => $responseData,
                    'created_at' => now(),
                    'started_at' => now(),
                    'completed_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("[MockAsteriskChatAdapter] No se pudo persistir AgentTask: " . $e->getMessage());
        }

        // 9. Audit completion
        AuditEvent::record(
            eventType: 'adapter.mock_conversational.completed',
            actionSummary: "Ejecución conversacional {$executionId} completada exitosamente",
            payload: [
                'execution_id' => $executionId,
                'thread_id' => $threadId,
                'correlation_id' => $correlationId,
                'agent_id' => $resolvedAgentId,
                'simulation_type' => $simulationType,
                'latency_ms' => $latencyMs,
            ],
            agent: $resolvedAgentId,
            ipAddress: $clientIp,
            userAgent: $userAgent
        );

        // 10. Serialize into specialized telephony / conversational output
        $body = $isAsterisk
            ? $this->serializeAsteriskOutput($executionId, $threadId, $callId, $channel, $callerId, $prompt, $outputText, $latencyMs, $responseData)
            : $this->serializeChatOutput($executionId, $threadId, $chatId, $channel, $callerName, $prompt, $outputText, $latencyMs, $responseData);

        return [
            'http_code' => 200,
            'headers' => ['X-Correlation-ID' => $correlationId],
            'body' => $body,
        ];
    }

    /**
     * Check if the incoming payload resembles an Asterisk PBX / Voice event.
     */
    protected function isAsteriskPayload(array $payload): bool
    {
        return isset($payload['call_id'])
            || isset($payload['caller_id'])
            || isset($payload['transcript'])
            || isset($payload['speech'])
            || isset($payload['extension'])
            || (isset($payload['channel']) && str_starts_with((string) $payload['channel'], 'SIP/'));
    }

    /**
     * Extract prompt text from conversational variations.
     */
    protected function extractConversationalPrompt(array $payload, bool $isAsterisk): string
    {
        if ($isAsterisk) {
            foreach (['transcript', 'speech', 'text', 'prompt', 'message'] as $key) {
                if (!empty($payload[$key]) && is_string($payload[$key])) {
                    return trim($payload[$key]);
                }
            }
            return "Llamada de voz Asterisk entrante sin transcripción de audio.";
        }

        foreach (['message', 'text', 'prompt', 'query', 'input'] as $key) {
            if (!empty($payload[$key]) && is_string($payload[$key])) {
                return trim($payload[$key]);
            }
        }

        if (isset($payload['message']['text']) && is_string($payload['message']['text'])) {
            return trim($payload['message']['text']);
        }

        return "Mensaje de chat conversacional recibido sin texto.";
    }

    /**
     * Resolve target agent, prioritizing the conversational agent runtime.
     */
    protected function resolveConversationalAgent(?string $targetAgentId): ?Agent
    {
        if (!empty($targetAgentId)) {
            return Agent::find($targetAgentId);
        }

        // Prioritize conversational agent by known UUID or name
        $agent = Agent::where('agent_id', 'a0000000-0000-0000-0000-000000000001')->active()->first();
        if ($agent) {
            return $agent;
        }

        $agentByName = Agent::where('name', 'like', '%conversacional%')->active()->first();
        if ($agentByName) {
            return $agentByName;
        }

        return Agent::active()->first();
    }

    /**
     * Extract primary string output from diverse agent payload patterns.
     */
    protected function extractOutputText(array $data): string
    {
        foreach (['output', 'result', 'response', 'answer', 'summary'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
                return trim($data[$key]);
            }
        }

        if (isset($data['result_json']['output']) && is_string($data['result_json']['output'])) {
            return trim($data['result_json']['output']);
        }

        return "Respuesta conversacional generada sin contenido de texto.";
    }

    /**
     * Serialize output for simulated Asterisk PBX / Voice client.
     */
    protected function serializeAsteriskOutput(
        string $executionId,
        string $threadId,
        ?string $callId,
        string $channel,
        string $callerId,
        string $prompt,
        string $outputText,
        float $latencyMs,
        array $rawData
    ): array {
        return [
            'status' => 'success',
            'simulation_type' => 'asterisk_voice',
            'call_id' => $callId ?? $threadId,
            'channel_id' => $channel,
            'caller_id' => $callerId,
            'tts_text' => $outputText,
            'response' => $outputText,
            'playback_action' => 'PLAY_TTS',
            'hangup' => false,
            'execution_id' => $executionId,
            'thread_id' => $threadId,
            'latency_ms' => $latencyMs,
            'transcript_turn' => [
                'input' => $prompt,
                'output' => $outputText,
            ],
            'debug' => [
                'steps_count' => count($rawData['cognitive_steps'] ?? $rawData['steps'] ?? []),
            ],
        ];
    }

    /**
     * Serialize output for simulated Web / Mobile Chat client.
     */
    protected function serializeChatOutput(
        string $executionId,
        string $threadId,
        ?string $chatId,
        string $channel,
        string $sender,
        string $prompt,
        string $outputText,
        float $latencyMs,
        array $rawData
    ): array {
        return [
            'status' => 'success',
            'simulation_type' => 'chat',
            'chat_id' => $chatId ?? $threadId,
            'channel' => $channel,
            'sender' => $sender,
            'text' => $outputText,
            'response' => $outputText,
            'execution_id' => $executionId,
            'thread_id' => $threadId,
            'latency_ms' => $latencyMs,
            'conversation' => [
                'last_message' => $prompt,
                'reply' => $outputText,
                'is_final' => true,
            ],
        ];
    }
}
