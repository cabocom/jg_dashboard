<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DevOps60kApiClient
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $timeout;
    protected int $retryTimes;
    protected int $retrySleepMs;

    public function __construct()
    {
        $this->baseUrl = rtrim(Config::get('devops60k.api_url', 'http://devops60k_app:8000'), '/');
        $this->apiKey = Config::get('devops60k.api_key', 'admin_key_pro');
        $this->timeout = (int) Config::get('devops60k.timeout', 15);
        $this->retryTimes = (int) Config::get('devops60k.retry_times', 2);
        $this->retrySleepMs = (int) Config::get('devops60k.retry_sleep_ms', 200);
    }

    /**
     * Helper to prepare HTTP client with auth headers and timeout.
     */
    protected function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retrySleepMs)
            ->withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
                'User-Agent' => 'DevOps60k-Web-Panel/1.0',
            ]);
    }

    /**
     * Check backend health and worker status.
     *
     * @return array{status: string, details?: array}
     */
    public function checkHealth(): array
    {
        try {
            $response = $this->client()->get('/health');
            if ($response->successful()) {
                return [
                    'online' => true,
                    'status' => $response->json('status', 'healthy'),
                    'data' => $response->json(),
                ];
            }

            return [
                'online' => false,
                'status' => 'http_error',
                'status_code' => $response->status(),
                'error' => $response->body(),
            ];
        } catch (Exception $e) {
            Log::warning('[DevOps60kApiClient] Healthcheck error: '.$e->getMessage());
            return [
                'online' => false,
                'status' => 'unreachable',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Trigger a new SRE agent task.
     *
     * @param string $prompt
     * @param string|null $userId
     * @param string|null $username
     * @param string $userRole
     * @return array
     * @throws Exception
     */
    public function triggerTask(string $prompt, ?string $userId = null, ?string $username = 'sre_operator', string $userRole = 'admin'): array
    {
        $payload = [
            'user_prompt' => $prompt,
            'user_id' => $userId ?? 'usr_web_'.uniqid(),
            'username' => $username,
            'user_role' => $userRole,
        ];

        $response = $this->client()->post('/trigger', $payload);

        if (!$response->successful()) {
            Log::error('[DevOps60kApiClient] Error encolando tarea', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception('Error al encolar tarea en DevOps60K: '.$response->body(), $response->status());
        }

        return $response->json();
    }

    /**
     * Approve a paused human-in-the-loop execution.
     *
     * @param string $threadId
     * @param string|null $notes
     * @param string $approver
     * @return array
     * @throws Exception
     */
    public function approveExecution(string $threadId, ?string $notes = null, string $approver = 'sre_operator'): array
    {
        $payload = [
            'approver' => $approver,
            'notes' => $notes ?? "Aprobado desde panel web por {$approver}",
        ];

        $response = $this->client()->post("/executions/{$threadId}/approve", $payload);

        if (!$response->successful()) {
            Log::error("[DevOps60kApiClient] Error aprobando ejecución {$threadId}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception("Error al aprobar ejecución ({$threadId}): ".$response->body(), $response->status());
        }

        return $response->json();
    }

    /**
     * Reject a paused human-in-the-loop execution.
     *
     * @param string $threadId
     * @param string|null $reason
     * @param string $rejector
     * @return array
     * @throws Exception
     */
    public function rejectExecution(string $threadId, ?string $reason = null, string $rejector = 'sre_operator'): array
    {
        $payload = [
            'reason' => $reason ?? "Rechazado desde panel web por {$rejector}",
            'rejector' => $rejector,
        ];

        $response = $this->client()->post("/executions/{$threadId}/reject", $payload);

        if (!$response->successful()) {
            Log::error("[DevOps60kApiClient] Error rechazando ejecución {$threadId}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception("Error al rechazar ejecución ({$threadId}): ".$response->body(), $response->status());
        }

        return $response->json();
    }

    /**
     * Fetch the complete execution lifecycle trace.
     *
     * @param string $threadId
     * @return array
     * @throws Exception
     */
    /**
     * Fetch the infrastructure topology map from backend agent.
     *
     * @param bool $refresh
     * @return array
     */
    public function getTopology(bool $refresh = false): array
    {
        try {
            $response = $this->client()->get('/topology', ['refresh' => $refresh ? 'true' : 'false']);
            if ($response->successful()) {
                return $response->json();
            }
        } catch (Exception $e) {
            Log::warning('[DevOps60kApiClient] Error fetching topology: '.$e->getMessage());
        }

        return [];
    }
}
