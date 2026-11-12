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

use App\Exceptions\AgentHttpException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class AgentHttpRequestDispatcher
{
    public function __construct(
        protected AgentHttpResponseHandler $responseHandler = new AgentHttpResponseHandler
    ) {}

    /**
     * Dispatch an HTTP request with retry logic and formatted error handling.
     *
     * @param array<string, string> $signatureHeaders
     * @return array{success: bool, status_code: int, data: ?array, error: ?string, latency_ms: float}
     */
    public function dispatch(
        string $targetUrl,
        string $method,
        string $endpoint,
        array $data,
        array $customHeaders,
        array $signatureHeaders,
        int $timeout,
        int $retryTimes,
        int $retrySleepMs,
        bool $verifySsl,
        ?string $agentId
    ): array {
        $startTime = microtime(true);
        $endpoint = '/'.ltrim($endpoint, '/');
        $isGet = strtoupper($method) === 'GET';
        $encodedBody = (! $isGet && ! empty($data)) ? json_encode($data, JSON_UNESCAPED_SLASHES) : '';

        $client = $this->prepareClient(
            $targetUrl,
            $encodedBody,
            array_merge($signatureHeaders, $customHeaders),
            $timeout,
            $retryTimes,
            $retrySleepMs,
            $verifySsl
        );

        try {
            $response = $this->executeCall($client, $method, $endpoint, $data, $encodedBody);
            if ($response->successful()) {
                return $this->responseHandler->formatSuccess($response, $startTime);
            }

            return $this->responseHandler->formatClientWarning($response, $method, $endpoint, $agentId, $startTime);
        } catch (RequestException $e) {
            return $this->responseHandler->handleRequestException($e, $method, $endpoint, $agentId, $startTime);
        } catch (ConnectionException $e) {
            return $this->responseHandler->handleConnectionException($e, $method, $endpoint, $agentId, $startTime);
        } catch (Throwable $e) {
            return $this->responseHandler->handleUnexpectedException($e, $method, $endpoint, $agentId, $startTime);
        }
    }

    /**
     * Build prepared PendingRequest.
     */
    protected function prepareClient(
        string $targetUrl,
        string $body,
        array $headers,
        int $timeout,
        int $retryTimes,
        int $retrySleepMs,
        bool $verifySsl
    ): PendingRequest {
        return Http::baseUrl(rtrim($targetUrl, '/'))
            ->timeout($timeout)
            ->withOptions(['verify' => $verifySsl])
            ->retry($retryTimes, $retrySleepMs, function ($exception) {
                return $exception instanceof ConnectionException ||
                    ($exception instanceof RequestException && $exception->response?->status() >= 500);
            }, throw: false)
            ->withHeaders($headers);
    }

    /**
     * Execute HTTP call on client.
     */
    protected function executeCall(
        PendingRequest $client,
        string $method,
        string $endpoint,
        array $data,
        string $encodedBody
    ): Response {
        $isGet = strtoupper($method) === 'GET';
        if ($encodedBody !== '') {
            $client = $client->withBody($encodedBody, 'application/json');
        } elseif (! $isGet) {
            $client = $client->withBody('', 'application/json');
        }

        return match (strtoupper($method)) {
            'GET' => $client->get($endpoint, $data),
            'POST' => $client->post($endpoint),
            'PUT' => $client->put($endpoint),
            'DELETE' => $client->delete($endpoint),
            default => throw new AgentHttpException("Método HTTP no soportado: {$method}"),
        };
    }
}

