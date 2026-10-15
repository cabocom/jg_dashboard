<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Adapters\Formatters;

use App\Adapters\Contracts\OutputFormatterInterface;

class ClientOutputFormatter implements OutputFormatterInterface
{
    /**
     * Format AgentOutput response into the desired client flavor.
     */
    public function format(array $agentResponse, string $format = 'generic', array $metadata = []): array
    {
        $success = (bool) ($agentResponse['success'] ?? false);
        $data = $agentResponse['data'] ?? [];
        $executionId = $data['execution_id'] ?? ($agentResponse['execution_id'] ?? ($metadata['execution_id'] ?? null));
        $threadId = $data['thread_id'] ?? ($agentResponse['thread_id'] ?? ($metadata['thread_id'] ?? null));
        $status = $data['status'] ?? ($agentResponse['status'] ?? ($success ? 'COMPLETED' : 'FAILED'));

        // Extract primary output / answer text
        $outputText = $this->extractOutputText($data, $agentResponse);

        return match (strtolower($format)) {
            'chatbot', 'chat', 'messaging' => $this->formatForChatbot($success, $status, $outputText, $executionId, $threadId, $data, $metadata),
            'ci_cd', 'cicd', 'pipeline', 'webhook' => $this->formatForCiCd($success, $status, $outputText, $executionId, $threadId, $data, $metadata),
            default => $this->formatGeneric($success, $status, $outputText, $executionId, $threadId, $data, $metadata),
        };
    }

    /**
     * Generic structured output for REST clients and APIs.
     */
    protected function formatGeneric(
        bool $success,
        string $status,
        string $outputText,
        ?string $executionId,
        ?string $threadId,
        array $data,
        array $metadata
    ): array {
        return [
            'success' => $success,
            'status' => $status,
            'execution_id' => $executionId,
            'thread_id' => $threadId,
            'output' => $outputText,
            'data' => $data,
            'metadata' => array_merge($metadata, [
                'formatted_by' => 'ClientOutputFormatter',
                'format' => 'generic',
            ]),
        ];
    }

    /**
     * Conversational output for chatbots, voice assistants and chat applications.
     */
    protected function formatForChatbot(
        bool $success,
        string $status,
        string $outputText,
        ?string $executionId,
        ?string $threadId,
        array $data,
        array $metadata
    ): array {
        return [
            'response' => $outputText,
            'text' => $outputText,
            'thread_id' => $threadId,
            'execution_id' => $executionId,
            'status' => strtolower($status),
            'success' => $success,
            'conversation' => [
                'chat_id' => $threadId,
                'is_final' => true,
            ],
        ];
    }

    /**
     * CI/CD formatted output for webhooks, automated jobs and pipeline triggers.
     */
    protected function formatForCiCd(
        bool $success,
        string $status,
        string $outputText,
        ?string $executionId,
        ?string $threadId,
        array $data,
        array $metadata
    ): array {
        return [
            'status' => $success ? 'success' : 'failure',
            'job_status' => $status,
            'summary' => $outputText,
            'execution_id' => $executionId,
            'thread_id' => $threadId,
            'pipeline_status' => $success ? 'PASSED' : 'FAILED',
            'details' => [
                'cognitive_steps_count' => count($data['cognitive_steps'] ?? $data['steps'] ?? []),
                'requires_approval' => (bool) ($data['requires_approval'] ?? false),
            ],
        ];
    }

    /**
     * Extract string output from diverse agent payload patterns.
     */
    protected function extractOutputText(array $data, array $agentResponse): string
    {
        // 1. Direct output fields
        foreach (['output', 'result', 'response', 'answer', 'summary'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
                return trim($data[$key]);
            }
            if (isset($agentResponse[$key]) && is_string($agentResponse[$key]) && trim($agentResponse[$key]) !== '') {
                return trim($agentResponse[$key]);
            }
        }

        // 2. Nested result_json or plan
        if (isset($data['result_json']['output']) && is_string($data['result_json']['output'])) {
            return trim($data['result_json']['output']);
        }

        if (isset($data['plan']['summary']) && is_string($data['plan']['summary'])) {
            return trim($data['plan']['summary']);
        }

        if (!empty($agentResponse['error'])) {
            return (string) $agentResponse['error'];
        }

        return "Ejecución finalizada sin contenido de texto devuelto.";
    }
}
