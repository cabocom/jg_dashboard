<?php

namespace App\Services\Agents;

use App\Exceptions\AgentConnectionException;
use App\Exceptions\AgentHttpException;
use App\Exceptions\AgentTimeoutException;
use App\Models\Agent;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

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

    public function __construct(
        ?string $baseUrl = null,
        ?string $internalSecret = null,
        ?int $timeout = null,
        ?int $retryTimes = null,
        ?int $retrySleepMs = null,
        ?bool $verifySsl = null,
        ?string $agentId = null
    ) {
        $this->baseUrl = rtrim(
            $baseUrl ?? Config::get('agent.base_url', Config::get('devops60k.api_url', 'http://jg_agente:8000')),
            '/'
        );
        $this->internalSecret = $internalSecret ?? Config::get('agent.internal_secret') ?? Config::get('devops60k.api_key');
        $this->timeout = $timeout ?? (int) Config::get('agent.timeout', 15);
        $this->retryTimes = $retryTimes ?? (int) Config::get('agent.retry_times', 2);
        $this->retrySleepMs = $retrySleepMs ?? (int) Config::get('agent.retry_sleep_ms', 200);
        $this->verifySsl = $verifySsl ?? (bool) Config::get('agent.verify_ssl', true);
        $this->agentId = $agentId;
    }

    /**
     * Factory to instantiate client specifically targeting a registered Agent.
     */
    public static function forAgent(Agent $agent): self
    {
        $instance = new self();
        $instance->agentId = $agent->agent_id;

        if (is_array($agent->metadata_json) && !empty($agent->metadata_json['api_url'])) {
            $instance->baseUrl = rtrim($agent->metadata_json['api_url'], '/');
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
        return $this;
    }

    public function withCorrelationId(string $correlationId): self
    {
        $this->correlationId = $correlationId;
        return $this;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getAgentId(): ?string
    {
        return $this->agentId;
    }

    /**
     * Generate security headers, HMAC signature and correlation tags
     * following the canonical contract:
     * canonical_string = METHOD\nPATH\nTIMESTAMP\nSHA256(RAW_BODY)
     */
    public function generateSignatureHeaders(string $body = '', string $method = 'POST', string $path = '/executions/trigger'): array
    {
        $timestamp = (string) time();
        $normalizedMethod = strtoupper(trim($method));
        $normalizedPath = '/' . ltrim($path, '/');
        $bodyHash = hash('sha256', $body);

        if (empty($this->internalSecret)) {
            throw new \RuntimeException("Seguridad (CWE-798): El secreto interno para firma HMAC no está configurado (JG_AGENTE_SECRET).");
        }

        $canonicalString = $normalizedMethod . "\n" . $normalizedPath . "\n" . $timestamp . "\n" . $bodyHash;
        $signature = hash_hmac('sha256', $canonicalString, $this->internalSecret);

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'jg_dashboard-ControlPlane/1.0',
            'X-Control-Plane' => 'jg_dashboard',
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
            'X-Correlation-ID' => $this->correlationId ?? (string) Str::uuid(),
        ];

        if ($this->agentId) {
            $headers['X-Agent-ID'] = $this->agentId;
        }

        return $headers;
    }

    /**
     * Build prepared PendingRequest with timeout, retries, SSL and signing headers.
     */
    protected function prepareClient(
        string $body = '',
        array $customHeaders = [],
        ?string $baseUrl = null,
        string $method = 'POST',
        string $endpoint = '/executions/trigger'
    ): PendingRequest {
        $targetUrl = $baseUrl ? rtrim($baseUrl, '/') : $this->baseUrl;
        $headers = array_merge($this->generateSignatureHeaders($body, $method, $endpoint), $customHeaders);

        return Http::baseUrl($targetUrl)
            ->timeout($this->timeout)
            ->withOptions(['verify' => $this->verifySsl])
            ->retry($this->retryTimes, $this->retrySleepMs, function ($exception) {
                return $exception instanceof ConnectionException ||
                    ($exception instanceof RequestException && $exception->response?->status() >= 500);
            }, throw: false)
            ->withHeaders($headers);
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
        $startTime = microtime(true);
        $endpoint = '/' . ltrim($endpoint, '/');
        $isGet = strtoupper($method) === 'GET';
        $encodedBody = (!$isGet && !empty($data)) ? json_encode($data, JSON_UNESCAPED_SLASHES) : '';
        $client = $this->prepareClient($encodedBody, $headers, $baseUrl, $method, $endpoint);

        try {
            /** @var Response $response */
            if ($encodedBody !== '') {
                $client = $client->withBody($encodedBody, 'application/json');
            }

            $response = match (strtoupper($method)) {
                'GET' => $client->get($endpoint, $data),
                'POST' => $client->post($endpoint),
                'PUT' => $client->put($endpoint),
                'DELETE' => $client->delete($endpoint),
                default => throw new AgentHttpException("Método HTTP no soportado: {$method}"),
            };

            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status_code' => $response->status(),
                    'data' => $response->json(),
                    'error' => null,
                    'latency_ms' => $latencyMs,
                ];
            }

            Log::warning('[AgentHttpClient] Error HTTP recibido de jg_agente', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
                'agent_id' => $this->agentId,
            ]);

            $detail = $response->json('detail');
            $detailStr = is_array($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : ($detail ?? $response->body());

            return [
                'success' => false,
                'status_code' => $response->status(),
                'data' => $response->json() ?? ['raw' => $response->body()],
                'error' => 'HTTP ' . $response->status() . ': ' . $detailStr,
                'latency_ms' => $latencyMs,
            ];
        } catch (RequestException $e) {
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
            $response = $e->response;
            $statusCode = $response ? $response->status() : 500;
            $detail = $response ? ($response->json('detail') ?? $response->body()) : $e->getMessage();
            $detailStr = is_array($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : (string) $detail;

            Log::warning('[AgentHttpClient] HTTP request exception de jg_agente', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $statusCode,
                'body' => $response ? $response->body() : null,
                'agent_id' => $this->agentId,
            ]);

            return [
                'success' => false,
                'status_code' => $statusCode,
                'data' => $response ? $response->json() : null,
                'error' => 'HTTP ' . $statusCode . ': ' . $detailStr,
                'latency_ms' => $latencyMs,
            ];
        } catch (ConnectionException $e) {
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
            $isTimeout = str_contains(strtolower($e->getMessage()), 'timed out') || str_contains(strtolower($e->getMessage()), 'timeout');

            Log::error('[AgentHttpClient] Fallo de conectividad con jg_agente', [
                'method' => $method,
                'endpoint' => $endpoint,
                'is_timeout' => $isTimeout,
                'message' => $e->getMessage(),
                'agent_id' => $this->agentId,
            ]);

            return [
                'success' => false,
                'status_code' => $isTimeout ? 504 : 503,
                'data' => null,
                'error' => $isTimeout ? 'Timeout al conectar con el agente' : 'Agente inaccesible o fallo de red: ' . $e->getMessage(),
                'latency_ms' => $latencyMs,
            ];
        } catch (Throwable $e) {
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('[AgentHttpClient] Excepción imprevista al comunicar con jg_agente', [
                'method' => $method,
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'agent_id' => $this->agentId,
            ]);

            return [
                'success' => false,
                'status_code' => 500,
                'data' => null,
                'error' => 'Error de comunicación interna: ' . $e->getMessage(),
                'latency_ms' => $latencyMs,
            ];
        }
    }

    /**
     * Check health status of the target agent service (/health or /).
     */
    public function checkHealth(?string $baseUrl = null): array
    {
        $result = $this->sendRequest('GET', '/health', [], [], $baseUrl);

        // Si /health responde 404 por versión previa, fallback a /
        if (!$result['success'] && $result['status_code'] === 404) {
            $result = $this->sendRequest('GET', '/', [], [], $baseUrl);
        }

        return $result;
    }

    /**
     * Fetch the agent capabilities, engines and tools catalog (/capabilities).
     */
    public function getCapabilities(?string $baseUrl = null): array
    {
        return $this->sendRequest('GET', '/capabilities', [], [], $baseUrl);
    }

    /**
     * Trigger a cognitive graph execution in jg_agente (POST /executions/trigger).
     */
    public function triggerExecution(array $payload, ?string $baseUrl = null, array $headers = []): array
    {
        return $this->sendRequest('POST', '/executions/trigger', $payload, $headers, $baseUrl);
    }

    /**
     * Inspect execution state and thread step trace (GET /executions/{thread_id}).
     */
    public function getExecutionStatus(string $threadId, ?string $baseUrl = null): array
    {
        return $this->sendRequest('GET', "/executions/{$threadId}", [], [], $baseUrl);
    }

    /**
     * Approve a paused human-in-the-loop checkpoint (POST /executions/{thread_id}/approve).
     */
    public function approveExecution(string $threadId, array $payload = [], ?string $baseUrl = null): array
    {
        return $this->sendRequest('POST', "/executions/{$threadId}/approve", $payload, [], $baseUrl);
    }

    /**
     * Reject and cancel a paused human-in-the-loop execution (POST /executions/{thread_id}/reject).
     */
    public function rejectExecution(string $threadId, array $payload = [], ?string $baseUrl = null): array
    {
        return $this->sendRequest('POST', "/executions/{$threadId}/reject", $payload, [], $baseUrl);
    }

    /**
     * Fetch runtime metrics from the agent (/metrics).
     */
    public function getMetrics(?string $baseUrl = null): array
    {
        return $this->sendRequest('GET', '/metrics', [], [], $baseUrl);
    }
}
