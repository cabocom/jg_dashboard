<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Services\Agents;

use App\Models\Agent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class AgentHttpClient
{
    protected string $baseUrl;

    protected ?string $internalSecret = null;

    protected int $timeout;

    protected int $retryTimes;

    protected int $retrySleepMs;

    protected bool $verifySsl;

    protected ?string $agentId = null;

    protected ?string $correlationId = null;

    protected AgentHmacSigner $signer;

    protected AgentHttpRequestDispatcher $dispatcher;

    public function __construct(
        ?string $baseUrl = null,
        ?string $internalSecret = null,
        ?int $timeout = null,
        ?int $retryTimes = null,
        ?int $retrySleepMs = null,
        ?bool $verifySsl = null,
        ?string $agentId = null,
        ?AgentHmacSigner $signer = null,
        ?AgentHttpRequestDispatcher $dispatcher = null
    ) {
        $this->signer = $signer ?? new AgentHmacSigner;
        $this->dispatcher = $dispatcher ?? new AgentHttpRequestDispatcher;
        $this->baseUrl = rtrim(
            $baseUrl ?? Config::get('agent.base_url', Config::get('devops60k.api_url', 'http://jg_agente:8000')),
            '/'
        );
        $this->agentId = $agentId ?? Config::get('agent.default_agent_id', 'a0000000-0000-0000-0000-000000000002');

        $this->internalSecret = $internalSecret ?? Config::get('agent.internal_secret') ?? Config::get('devops60k.api_key');
        if (empty($this->internalSecret) && $this->agentId === 'a0000000-0000-0000-0000-000000000002') {
            $this->internalSecret = Config::get('agent.devops_secret');
        }

        $this->timeout = $timeout ?? (int) (Config::get('agent.timeout') ?: 120);
        $this->retryTimes = $retryTimes ?? (int) Config::get('agent.retry_times', 2);
        $this->retrySleepMs = $retrySleepMs ?? (int) Config::get('agent.retry_sleep_ms', 200);
        $this->verifySsl = $verifySsl ?? (bool) Config::get('agent.verify_ssl', true);
    }

    /**
     * Factory to instantiate client specifically targeting a registered Agent.
     */
    public static function forAgent(Agent $agent): self
    {
        $instance = new self;
        $instance->agentId = $agent->agent_id;

        if (is_array($agent->metadata_json) && ! empty($agent->metadata_json['api_url'])) {
            $instance->baseUrl = rtrim($agent->metadata_json['api_url'], '/');
        }

        $secret = $agent->resolveHmacSecret();
        if (! empty($secret)) {
            $instance->internalSecret = $secret;
        }

        return $instance;
    }

    public function withBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = rtrim($baseUrl, '/');

        return $this;
    }

    public function withSecret(string $secret): self
    {
        $this->internalSecret = $secret;

        return $this;
    }

    public function withAgentId(string $agentId): self
    {
        $this->agentId = $agentId;

        if ($agentId === 'a0000000-0000-0000-0000-000000000002') {
            $this->internalSecret = Config::get('agent.devops_secret');
        } elseif ($agentId === 'a0000000-0000-0000-0000-000000000001') {
            $this->internalSecret = Config::get('agent.conversational_secret') ?: Config::get('agent.internal_secret');
        } else {
            $this->internalSecret = Config::get('agent.secrets.'.$agentId) ?: Config::get('agent.internal_secret');
        }

        return $this;
    }

    public function withCorrelationId(string $correlationId): self
    {
        $this->correlationId = $correlationId;

        return $this;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getAgentId(): ?string
    {
        return $this->agentId;
    }

    public function withTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Generate security headers, HMAC signature and correlation tags.
     *
     * @return array<string, string>
     */
    public function generateSignatureHeaders(
        string $body = '',
        string $method = 'POST',
        string $path = '/executions/trigger',
        ?string $nonce = null
    ): array {
        return $this->signer->generateSignatureHeaders(
            $body,
            $method,
            $path,
            $this->internalSecret,
            $this->agentId,
            $this->correlationId,
            $nonce
        );
    }

    /**
     * Execute a resilient HTTP request and format response safely.
     *
     * @return array{success: bool, status_code: int, data: ?array, error: ?string, latency_ms: float}
     */
    public function sendRequest(
        string $method,
        string $endpoint,
        array $data = [],
        array $headers = [],
        ?string $baseUrl = null
    ): array {
        $targetUrl = $baseUrl ? rtrim($baseUrl, '/') : $this->baseUrl;
        $isGet = strtoupper($method) === 'GET';
        $encodedBody = (! $isGet && ! empty($data)) ? json_encode($data, JSON_UNESCAPED_SLASHES) : '';
        $normalizedEndpoint = '/'.ltrim($endpoint, '/');

        $sigHeaders = $this->generateSignatureHeaders($encodedBody, $method, $normalizedEndpoint);

        return $this->dispatcher->dispatch(
            $targetUrl,
            $method,
            $normalizedEndpoint,
            $data,
            $headers,
            $sigHeaders,
            $this->timeout,
            $this->retryTimes,
            $this->retrySleepMs,
            $this->verifySsl,
            $this->agentId
        );
    }

    public function checkHealth(?string $baseUrl = null): array
    {
        $result = $this->sendRequest('GET', '/health', [], [], $baseUrl);
        if (! $result['success'] && $result['status_code'] === 404) {
            $result = $this->sendRequest('GET', '/', [], [], $baseUrl);
        }

        return $result;
    }

    public function getCapabilities(?string $baseUrl = null): array
    {
        return $this->sendRequest('GET', '/capabilities', [], [], $baseUrl);
    }

    public function triggerExecution(array $payload, ?string $baseUrl = null, array $headers = []): array
    {
        if (empty($payload['execution_id'])) {
            $payload['execution_id'] = (string) Str::uuid();
        }
        if (empty($payload['thread_id'])) {
            $payload['thread_id'] = (string) Str::uuid();
        }

        return $this->sendRequest('POST', '/executions/trigger', $payload, $headers, $baseUrl);
    }

    public function getExecutionStatus(string $threadId, ?string $baseUrl = null): array
    {
        return $this->sendRequest('GET', "/executions/{$threadId}", [], [], $baseUrl);
    }

    public function approveExecution(string $threadId, array $payload = [], ?string $baseUrl = null): array
    {
        $payload['thread_id'] = $threadId;
        $payload['approved'] = true;

        return $this->sendRequest('POST', "/executions/{$threadId}/approve", $payload, [], $baseUrl);
    }

    public function rejectExecution(string $threadId, array $payload = [], ?string $baseUrl = null): array
    {
        $payload['thread_id'] = $threadId;
        $payload['approved'] = false;
        $payload['rejected'] = true;

        return $this->sendRequest('POST', "/executions/{$threadId}/reject", $payload, [], $baseUrl);
    }

    public function getMetrics(?string $baseUrl = null): array
    {
        return $this->sendRequest('GET', '/metrics', [], [], $baseUrl);
    }
}
