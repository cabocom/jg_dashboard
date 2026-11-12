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

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class AgentHttpResponseHandler
{
    /**
     * Format successful response payload.
     *
     * @return array{success: bool, status_code: int, data: ?array, error: ?string, latency_ms: float}
     */
    public function formatSuccess(Response $response, float $startTime): array
    {
        return [
            'success' => true,
            'status_code' => $response->status(),
            'data' => $response->json(),
            'error' => null,
            'latency_ms' => $this->calculateLatency($startTime),
        ];
    }

    /**
     * Format HTTP error warning response.
     *
     * @return array{success: bool, status_code: int, data: ?array, error: ?string, latency_ms: float}
     */
    public function formatClientWarning(
        Response $response,
        string $method,
        string $endpoint,
        ?string $agentId,
        float $startTime
    ): array {
        Log::warning('[AgentHttpClient] Error HTTP recibido de jg_agente', [
            'method' => $method,
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'body' => $response->body(),
            'agent_id' => $agentId,
        ]);

        $detail = $response->json('detail');
        $detailStr = is_array($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : ($detail ?? $response->body());

        return [
            'success' => false,
            'status_code' => $response->status(),
            'data' => $response->json() ?? ['raw' => $response->body()],
            'error' => 'HTTP '.$response->status().': '.$detailStr,
            'latency_ms' => $this->calculateLatency($startTime),
        ];
    }

    /**
     * Handle RequestException error payload.
     *
     * @return array{success: bool, status_code: int, data: ?array, error: ?string, latency_ms: float}
     */
    public function handleRequestException(
        RequestException $e,
        string $method,
        string $endpoint,
        ?string $agentId,
        float $startTime
    ): array {
        $response = $e->response;
        $statusCode = $response ? $response->status() : 500;
        $detail = $response ? ($response->json('detail') ?? $response->body()) : $e->getMessage();
        $detailStr = is_array($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : (string) $detail;

        Log::warning('[AgentHttpClient] HTTP request exception de jg_agente', [
            'method' => $method,
            'endpoint' => $endpoint,
            'status' => $statusCode,
            'body' => $response ? $response->body() : null,
            'agent_id' => $agentId,
        ]);

        return [
            'success' => false,
            'status_code' => $statusCode,
            'data' => $response ? $response->json() : null,
            'error' => 'HTTP '.$statusCode.': '.$detailStr,
            'latency_ms' => $this->calculateLatency($startTime),
        ];
    }

    /**
     * Handle ConnectionException error payload.
     *
     * @return array{success: bool, status_code: int, data: ?array, error: ?string, latency_ms: float}
     */
    public function handleConnectionException(
        ConnectionException $e,
        string $method,
        string $endpoint,
        ?string $agentId,
        float $startTime
    ): array {
        $isTimeout = str_contains(strtolower($e->getMessage()), 'timed out') || str_contains(strtolower($e->getMessage()), 'timeout');

        Log::error('[AgentHttpClient] Fallo de conectividad con jg_agente', [
            'method' => $method,
            'endpoint' => $endpoint,
            'is_timeout' => $isTimeout,
            'message' => $e->getMessage(),
            'agent_id' => $agentId,
        ]);

        return [
            'success' => false,
            'status_code' => $isTimeout ? 504 : 503,
            'data' => null,
            'error' => $isTimeout ? 'Timeout al conectar con el agente' : 'Agente inaccesible o fallo de red: '.$e->getMessage(),
            'latency_ms' => $this->calculateLatency($startTime),
        ];
    }

    /**
     * Handle unexpected Throwable error payload.
     *
     * @return array{success: bool, status_code: int, data: ?array, error: ?string, latency_ms: float}
     */
    public function handleUnexpectedException(
        Throwable $e,
        string $method,
        string $endpoint,
        ?string $agentId,
        float $startTime
    ): array {
        Log::error('[AgentHttpClient] Excepción imprevista al comunicar con jg_agente', [
            'method' => $method,
            'endpoint' => $endpoint,
            'message' => $e->getMessage(),
            'file' => $e->getFile().':'.$e->getLine(),
            'trace' => $e->getTraceAsString(),
            'agent_id' => $agentId,
        ]);

        return [
            'success' => false,
            'status_code' => 500,
            'data' => null,
            'error' => 'Error de comunicación interna: '.$e->getMessage(),
            'latency_ms' => $this->calculateLatency($startTime),
        ];
    }

    /**
     * Calculate elapsed latency in milliseconds.
     */
    protected function calculateLatency(float $startTime): float
    {
        return round((microtime(true) - $startTime) * 1000, 2);
    }
}

