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

class MockConversationalPayloadFormatter
{
    /**
     * Check if the incoming payload resembles an Asterisk PBX / Voice event.
     */
    public function isAsteriskPayload(array $payload): bool
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
    public function extractConversationalPrompt(array $payload, bool $isAsterisk): string
    {
        if ($isAsterisk) {
            foreach (['transcript', 'speech', 'text', 'prompt', 'message'] as $key) {
                if (!empty($payload[$key]) && is_string($payload[$key])) {
                    return trim($payload[$key]);
                }
            }
            return 'Llamada de voz Asterisk entrante sin transcripción de audio.';
        }

        foreach (['message', 'text', 'prompt', 'query', 'input'] as $key) {
            if (!empty($payload[$key]) && is_string($payload[$key])) {
                return trim($payload[$key]);
            }
        }

        if (isset($payload['message']['text']) && is_string($payload['message']['text'])) {
            return trim($payload['message']['text']);
        }

        return 'Mensaje de chat conversacional recibido sin texto.';
    }

    /**
     * Extract primary string output from diverse agent payload patterns.
     */
    public function extractOutputText(array $data): string
    {
        foreach (['output', 'result', 'response', 'answer', 'summary'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
                return trim($data[$key]);
            }
        }

        if (isset($data['result_json']['output']) && is_string($data['result_json']['output'])) {
            return trim($data['result_json']['output']);
        }

        return 'Respuesta conversacional generada sin contenido de texto.';
    }

    /**
     * Build canonical AgentInput array for upstream execution.
     */
    public function buildAgentInput(
        string $executionId,
        string $threadId,
        string $correlationId,
        string $resolvedAgentId,
        string $prompt,
        string $simulationType,
        string $channel,
        string $callerId,
        string $callerName,
        bool $isAsterisk,
        ?string $callId,
        ?string $chatId,
        string $clientIp,
        string $userAgent,
        array $parameters = []
    ): array {
        return [
            'execution_id' => $executionId,
            'thread_id' => $threadId,
            'correlation_id' => $correlationId,
            'agent_id' => $resolvedAgentId,
            'prompt' => $prompt,
            'user_prompt' => $prompt,
            'parameters' => (object) array_merge(
                $parameters,
                [
                    'simulation_type' => $simulationType,
                    'channel' => $channel,
                    'caller_id' => $callerId,
                    'short_answer' => $isAsterisk,
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
    }

    /**
     * Serialize output for simulated Asterisk PBX / Voice client.
     */
    public function serializeAsteriskOutput(
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
        $cleanOutput = $this->sanitizeShortAnswer($outputText);

        return [
            'status' => 'success',
            'simulation_type' => 'asterisk_voice',
            'call_id' => $callId ?? $threadId,
            'channel_id' => $channel,
            'caller_id' => $callerId,
            'tts_text' => $cleanOutput,
            'response' => $cleanOutput,
            'playback_action' => 'PLAY_TTS',
            'hangup' => false,
            'execution_id' => $executionId,
            'thread_id' => $threadId,
            'latency_ms' => $latencyMs,
            'transcript_turn' => [
                'input' => $prompt,
                'output' => $cleanOutput,
            ],
            'debug' => [
                'steps_count' => count($rawData['cognitive_steps'] ?? $rawData['steps'] ?? []),
            ],
        ];
    }

    /**
     * Limpia y formatea el texto en modalidad de respuesta corta (sin URLs, fuentes ni markdown).
     */
    public function sanitizeShortAnswer(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        // 1. Eliminar URLs completas
        $cleaned = (string) preg_replace('/https?:\/\/\S+/', '', $text);
        // 2. Transformar enlaces markdown [texto](url) -> texto
        $cleaned = (string) preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $cleaned);
        // 3. Eliminar menciones y citas de fuentes
        $cleaned = (string) preg_replace('/(?i)\(?(?:fuente|fuentes|disponible en|según datos de)\s*:\s*[^\n.)]*\)?/', '', $cleaned);
        // 4. Eliminar sintaxis markdown (negritas, encabezados, viñetas)
        $cleaned = (string) preg_replace('/[*_#`~]+/', '', $cleaned);
        $cleaned = (string) preg_replace('/^\s*[-•*]\s+/m', '', $cleaned);

        // 5. Normalizar espacios en blanco
        return trim((string) preg_replace('/\s+/', ' ', $cleaned));
    }

    /**
     * Alias retrocompatible para sanitizeShortAnswer.
     */
    public function sanitizeForVoiceTts(string $text): string
    {
        return $this->sanitizeShortAnswer($text);
    }

    /**
     * Serialize output for simulated Web / Mobile Chat client.
     */
    public function serializeChatOutput(
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
