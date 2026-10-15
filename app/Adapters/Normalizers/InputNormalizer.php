<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Adapters\Normalizers;

use App\Adapters\Contracts\InputNormalizerInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InputNormalizer implements InputNormalizerInterface
{
    /**
     * Normalize an arbitrary external stimulus into a canonical AgentInput array contract.
     */
    public function normalize(Request|array $stimulus, array $context = []): array
    {
        $payload = $stimulus instanceof Request ? $stimulus->all() : $stimulus;
        $headers = $stimulus instanceof Request ? $stimulus->headers->all() : ($context['headers'] ?? []);

        // 1. Detect stimulus source / channel type
        $sourceType = $this->detectSourceType($payload, $headers, $context);

        // 2. Extract normalized prompt
        $prompt = $this->extractPrompt($payload, $sourceType);

        // 3. Extract or generate consistent IDs
        $executionId = !empty($payload['execution_id']) ? (string) $payload['execution_id'] : (string) Str::uuid();
        $threadId = $this->extractThreadId($payload, $context);
        $correlationId = $this->extractCorrelationId($payload, $headers, $context);

        // 4. Extract execution parameters
        $parameters = $this->extractParameters($payload);

        // 5. Build user context for external caller
        $userContext = $this->buildUserContext($payload, $context, $sourceType);

        // 6. Build audit and provenance metadata
        $metadata = $this->buildMetadata($payload, $headers, $context, $sourceType, $correlationId);

        return [
            'execution_id' => $executionId,
            'thread_id' => $threadId,
            'correlation_id' => $correlationId,
            'prompt' => $prompt,
            'user_prompt' => $prompt,
            'parameters' => (object) $parameters,
            'user_context' => $userContext,
            'metadata' => (object) $metadata,
        ];
    }

    /**
     * Detect the source kind of the external stimulus.
     */
    protected function detectSourceType(array $payload, array $headers, array $context): string
    {
        if (!empty($context['source'])) {
            return (string) $context['source'];
        }

        if (!empty($payload['source'])) {
            return (string) $payload['source'];
        }

        // GitLab Webhook detection
        if (isset($headers['x-gitlab-event']) || isset($payload['object_kind'])) {
            return 'gitlab';
        }

        // GitHub Webhook detection
        if (isset($headers['x-github-event']) || isset($headers['x-github-delivery'])) {
            return 'github';
        }

        // Slack Webhook detection
        if (isset($payload['command']) || isset($payload['event']['type']) || isset($headers['x-slack-signature'])) {
            return 'slack';
        }

        // Telegram Webhook detection
        if (isset($payload['update_id']) && (isset($payload['message']) || isset($payload['callback_query']))) {
            return 'telegram';
        }

        // Chatbot / Conversational payload
        if (isset($payload['chat_id']) || isset($payload['conversation_id'])) {
            return 'chatbot';
        }

        // CI/CD generic detection
        if (isset($payload['pipeline_id']) || isset($payload['build_id']) || isset($payload['ci_event'])) {
            return 'ci_cd';
        }

        return 'generic';
    }

    /**
     * Extract or synthesize a meaningful prompt text from arbitrary payloads.
     */
    protected function extractPrompt(array $payload, string $sourceType): string
    {
        // 1. Direct explicit prompts
        foreach (['prompt', 'user_prompt', 'message', 'text', 'content', 'query', 'input'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key]) && trim($payload[$key]) !== '') {
                return trim($payload[$key]);
            }
        }

        // 2. Chatbot nested formats (e.g. { message: { text: "..." } })
        if (isset($payload['message']) && is_array($payload['message'])) {
            if (!empty($payload['message']['text']) && is_string($payload['message']['text'])) {
                return trim($payload['message']['text']);
            }
        }

        // 3. Slack event format (e.g. { event: { text: "..." } })
        if (isset($payload['event']['text']) && is_string($payload['event']['text'])) {
            return trim($payload['event']['text']);
        }

        // 4. GitLab Webhook synthesis
        if ($sourceType === 'gitlab' || isset($payload['object_kind'])) {
            $kind = $payload['object_kind'] ?? 'webhook';
            $project = $payload['project']['name'] ?? $payload['project']['path_with_namespace'] ?? 'Proyecto GitLab';
            $user = $payload['user_name'] ?? $payload['user_username'] ?? 'CI/CD';

            if ($kind === 'push') {
                $ref = $payload['ref'] ?? 'refs/heads/main';
                $commitMsg = $payload['commits'][0]['message'] ?? 'Sin mensaje de commit';
                return "[GitLab CI/CD: Push] {$project} ({$ref}) por {$user}: {$commitMsg}";
            }

            if ($kind === 'pipeline') {
                $status = $payload['object_attributes']['status'] ?? 'unknown';
                $ref = $payload['object_attributes']['ref'] ?? 'unknown';
                return "[GitLab CI/CD: Pipeline] {$project} ({$ref}) estado '{$status}' disparado por {$user}";
            }

            if ($kind === 'merge_request') {
                $title = $payload['object_attributes']['title'] ?? 'Merge Request';
                $action = $payload['object_attributes']['action'] ?? 'actualizado';
                return "[GitLab CI/CD: MR] {$project} MR '{$title}' ({$action}) por {$user}";
            }

            return "[GitLab Webhook: {$kind}] Evento en {$project} por {$user}";
        }

        // 5. GitHub Webhook synthesis
        if ($sourceType === 'github') {
            $repo = $payload['repository']['full_name'] ?? 'GitHub Repo';
            $sender = $payload['sender']['login'] ?? 'GitHub User';

            if (isset($payload['commits'])) {
                $commitMsg = $payload['commits'][0]['message'] ?? ($payload['head_commit']['message'] ?? 'Push');
                return "[GitHub CI/CD: Push] {$repo} por {$sender}: {$commitMsg}";
            }

            if (isset($payload['pull_request'])) {
                $action = $payload['action'] ?? 'opened';
                $title = $payload['pull_request']['title'] ?? 'Pull Request';
                return "[GitHub CI/CD: PR] {$repo} PR '{$title}' ({$action}) por {$sender}";
            }

            return "[GitHub Webhook] Evento en {$repo} por {$sender}";
        }

        // 6. Generic fallback if json object has raw parameters
        if (!empty($payload)) {
            $summary = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return "[External Webhook Stimulus] " . Str::limit($summary, 500);
        }

        return "External stimulus received without prompt text.";
    }

    /**
     * Extract or generate a consistent thread ID across interactions.
     */
    protected function extractThreadId(array $payload, array $context): string
    {
        // Check explicit keys
        foreach (['thread_id', 'chat_id', 'conversation_id', 'channel_id', 'session_id', 'pipeline_id'] as $key) {
            if (!empty($payload[$key]) && (is_string($payload[$key]) || is_numeric($payload[$key]))) {
                return (string) $payload[$key];
            }
        }

        // Check nested chatbot chat ID (Telegram: message.chat.id)
        if (!empty($payload['message']['chat']['id'])) {
            return (string) $payload['message']['chat']['id'];
        }

        // Check context
        if (!empty($context['thread_id'])) {
            return (string) $context['thread_id'];
        }

        return (string) Str::uuid();
    }

    /**
     * Extract or generate correlation ID.
     */
    protected function extractCorrelationId(array $payload, array $headers, array $context): string
    {
        // 1. Check HTTP header X-Correlation-ID
        if (!empty($headers['x-correlation-id'][0])) {
            return (string) $headers['x-correlation-id'][0];
        }

        // 2. Check context
        if (!empty($context['correlation_id'])) {
            return (string) $context['correlation_id'];
        }

        // 3. Check payload key
        if (!empty($payload['correlation_id']) && is_string($payload['correlation_id'])) {
            return (string) $payload['correlation_id'];
        }

        return (string) Str::uuid();
    }

    /**
     * Extract and normalize parameters.
     */
    protected function extractParameters(array $payload): array
    {
        $parameters = is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [];

        // Map boolean async flags
        if (isset($payload['async'])) {
            $parameters['async'] = filter_var($payload['async'], FILTER_VALIDATE_BOOLEAN);
        } elseif (isset($payload['is_async'])) {
            $parameters['async'] = filter_var($payload['is_async'], FILTER_VALIDATE_BOOLEAN);
        }

        // Transfer other relevant top-level parameters
        foreach (['model', 'engine', 'temperature', 'max_tokens', 'tags', 'format'] as $key) {
            if (isset($payload[$key]) && !isset($parameters[$key])) {
                $parameters[$key] = $payload[$key];
            }
        }

        return $parameters;
    }

    /**
     * Build external client user context.
     */
    protected function buildUserContext(array $payload, array $context, string $sourceType): array
    {
        $clientId = $payload['client_id']
            ?? $payload['sender_id']
            ?? $context['client_id']
            ?? $context['ip']
            ?? 'external-client';

        $clientName = $payload['sender_name']
            ?? $payload['user_name']
            ?? $payload['sender']['login']
            ?? "Adapter Client ({$sourceType})";

        return [
            'user_id' => (string) $clientId,
            'username' => (string) $clientName,
            'email' => $payload['email'] ?? "{$sourceType}@adapter.local",
            'role' => 'external_client',
            'source' => $sourceType,
            'permissions' => ['agent:execute'],
        ];
    }

    /**
     * Build rich metadata for audit and observability.
     */
    protected function buildMetadata(
        array $payload,
        array $headers,
        array $context,
        string $sourceType,
        string $correlationId
    ): array {
        $meta = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        return array_merge($meta, [
            'adapter' => 'http_webhook',
            'source' => $sourceType,
            'correlation_id' => $correlationId,
            'client_ip' => $context['ip'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'received_at' => now()->toIso8601String(),
            'agent_id' => $payload['agent_id'] ?? ($context['agent_id'] ?? null),
        ]);
    }
}
