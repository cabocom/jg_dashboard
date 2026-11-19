<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\ApprovalRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake();

    $this->plainSecret = 'sec_' . bin2hex(random_bytes(24));
    $this->secretHash = hash('sha256', $this->plainSecret);

    $this->agent = Agent::create([
        'name' => 'async-webhook-agent',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => $this->secretHash,
        'permanent_secret_encrypted' => $this->plainSecret,
        'last_heartbeat_at' => now(),
    ]);

    $this->executionId = 'exec-async-hook-001';
    $this->threadId = 'th-async-hook-001';

    // Pre-crear la tarea en estado QUEUED como lo haría triggerExecution async
    $this->task = AgentTask::create([
        'task_id' => $this->executionId,
        'thread_id' => $this->threadId,
        'agent_id' => $this->agent->agent_id,
        'status' => 'QUEUED',
        'payload_json' => ['prompt' => 'Tarea pesada en segundo plano'],
        'created_at' => now()->subSeconds(10),
        'started_at' => now()->subSeconds(10),
    ]);

    $this->makeHmacHeaders = function (string $path, array $payload, ?string $secret = null, ?string $agentId = null, ?int $timestamp = null) {
        $sec = $secret ?? $this->plainSecret;
        $aid = $agentId ?? $this->agent->agent_id;
        $ts = (string) ($timestamp ?? time());
        $body = json_encode($payload);
        $bodyHash = hash('sha256', (string) $body);
        $canonical = "POST\n{$path}\n{$ts}\n{$bodyHash}";
        $sig = hash_hmac('sha256', $canonical, $sec);

        return [
            'X-Signature' => $sig,
            'X-Timestamp' => $ts,
            'X-Agent-ID' => $aid,
        ];
    };
});

test('POST /api/v1/internal/executions/{id}/complete returns 401 when agent_id or secret is missing', function () {
    // Missing all credentials
    $this->postJson("/api/v1/internal/executions/{$this->executionId}/complete", [
        'status' => 'completed',
    ])->assertStatus(401);

    // Missing secret
    $this->withHeader('X-Agent-ID', $this->agent->agent_id)
        ->postJson("/api/v1/internal/executions/{$this->executionId}/complete", [
            'status' => 'completed',
        ])->assertStatus(401);
});

test('POST /api/v1/internal/executions/{id}/complete returns 401 when secret is invalid', function () {
    $this->withHeaders([
        'X-Agent-ID' => $this->agent->agent_id,
        'Authorization' => 'Bearer wrong_secret',
    ])->postJson("/api/v1/internal/executions/{$this->executionId}/complete", [
        'status' => 'completed',
    ])->assertStatus(401);
});

test('POST /api/v1/internal/executions/{id}/complete returns 404 when agent does not exist', function () {
    $this->withHeaders([
        'X-Agent-ID' => 'non-existent-agent-id',
        'Authorization' => "Bearer {$this->plainSecret}",
    ])->postJson("/api/v1/internal/executions/{$this->executionId}/complete", [
        'status' => 'completed',
    ])->assertStatus(404);
});

test('POST /api/v1/internal/executions/{id}/complete updates task to COMPLETED on success', function () {
    $response = $this->withHeaders([
        'X-Agent-ID' => $this->agent->agent_id,
        'Authorization' => "Bearer {$this->plainSecret}",
        'X-Correlation-ID' => 'cid-webhook-test-123',
    ])->postJson("/api/v1/internal/executions/{$this->executionId}/complete", [
        'execution_id' => $this->executionId,
        'thread_id' => $this->threadId,
        'status' => 'completed',
        'response' => 'Operación asíncrona finalizada con éxito.',
        'data' => ['processed' => 100],
        'execution_time_ms' => 350.5,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'execution_id' => $this->executionId,
            'task_status' => 'COMPLETED',
        ]);

    $refreshedTask = $this->task->fresh();
    expect($refreshedTask->status)->toBe('COMPLETED')
        ->and($refreshedTask->completed_at)->not->toBeNull()
        ->and($refreshedTask->result_json['response'])->toBe('Operación asíncrona finalizada con éxito.');
});

test('POST /api/v1/internal/executions/{id}/complete updates task to FAILED on failure payload', function () {
    $response = $this->withHeaders([
        'X-Agent-ID' => $this->agent->agent_id,
        'X-Agent-Secret' => $this->plainSecret,
    ])->postJson("/api/v1/internal/executions/{$this->executionId}/complete", [
        'execution_id' => $this->executionId,
        'thread_id' => $this->threadId,
        'status' => 'failed',
        'response' => 'Fallo crítico durante ejecución asíncrona.',
        'data' => ['error' => 'Timeout excedido'],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'task_status' => 'FAILED',
        ]);

    $refreshedTask = $this->task->fresh();
    expect($refreshedTask->status)->toBe('FAILED')
        ->and($refreshedTask->completed_at)->not->toBeNull();
});

test('POST /api/v1/internal/executions/{id}/complete registers HITL approval when requires_approval is true', function () {
    $response = $this->withHeaders([
        'X-Agent-ID' => $this->agent->agent_id,
        'Authorization' => "Bearer {$this->plainSecret}",
    ])->postJson("/api/v1/internal/executions/{$this->executionId}/complete", [
        'execution_id' => $this->executionId,
        'thread_id' => $this->threadId,
        'status' => 'waiting_approval',
        'requires_approval' => true,
        'pending_approval_id' => 'human_approval_required',
        'response' => 'Operación de alto riesgo requiere aprobación.',
        'data' => [
            'risk_assessment' => ['risk_level' => 'HIGH'],
            'plan' => ['step' => 'Reiniciar contenedor de producción'],
            'cognitive_steps' => [
                ['name' => 'plan_approval', 'description' => 'Evaluación de riesgo completada'],
            ],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'task_status' => 'WAITING_APPROVAL',
        ]);

    $refreshedTask = $this->task->fresh();
    expect($refreshedTask->status)->toBe('WAITING_APPROVAL');

    $approval = ApprovalRequest::where('thread_id', $this->threadId)->first();
    expect($approval)->not->toBeNull()
        ->and($approval->status)->toBe('PENDING')
        ->and($approval->risk_level)->toBe('HIGH');
});

test('unversioned route /api/internal/executions/{id}/complete returns 404 not found (CWE-668)', function () {
    $this->withHeaders([
        'X-Agent-ID' => $this->agent->agent_id,
        'Authorization' => "Bearer {$this->plainSecret}",
    ])->postJson("/api/internal/executions/{$this->executionId}/complete", [
        'execution_id' => $this->executionId,
        'thread_id' => $this->threadId,
        'status' => 'completed',
    ])->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| Tests para DASH-CODE-02: Endpoint HMAC /status-update para tareas asíncronas
|--------------------------------------------------------------------------
*/

test('POST /api/v1/internal/executions/{id}/status-update returns 401 when HMAC headers are missing', function () {
    $this->postJson("/api/v1/internal/executions/{$this->executionId}/status-update", [
        'status' => 'in_progress',
        'current_step' => 1,
    ])->assertStatus(401)
      ->assertJson([
          'error' => 'Unauthorized',
          'message' => 'Cabeceras de autenticación HMAC requeridas (X-Signature, X-Timestamp, X-Agent-ID)',
      ]);
});

test('POST /api/v1/internal/executions/{id}/status-update returns 401 when HMAC signature is invalid', function () {
    $path = "/api/v1/internal/executions/{$this->executionId}/status-update";
    $payload = ['status' => 'in_progress', 'step' => 2];
    $headers = ($this->makeHmacHeaders)($path, $payload, 'wrong_secret_key');

    $this->withHeaders($headers)
        ->postJson($path, $payload)
        ->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Firma HMAC-SHA256 inválida.',
        ]);
});

test('POST /api/v1/internal/executions/{id}/status-update returns 401 when timestamp is outside tolerance window', function () {
    $path = "/api/v1/internal/executions/{$this->executionId}/status-update";
    $payload = ['status' => 'in_progress'];
    // Timestamp from 10 minutes ago (> 300s)
    $headers = ($this->makeHmacHeaders)($path, $payload, null, null, time() - 600);

    $this->withHeaders($headers)
        ->postJson($path, $payload)
        ->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Timestamp fuera de la ventana de tolerancia temporal (300s)',
        ]);
});

test('POST /api/v1/internal/executions/{id}/status-update updates task to PROCESSING with steps and node', function () {
    $path = "/api/v1/internal/executions/{$this->executionId}/status-update";
    $payload = [
        'execution_id' => $this->executionId,
        'thread_id' => $this->threadId,
        'status' => 'in_progress',
        'current_step' => 2,
        'node' => 'reasoning',
        'cognitive_steps' => [
            ['name' => 'observation', 'status' => 'done'],
            ['name' => 'reasoning', 'status' => 'running'],
        ],
    ];
    $headers = ($this->makeHmacHeaders)($path, $payload);

    $response = $this->withHeaders($headers)->postJson($path, $payload);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'execution_id' => $this->executionId,
            'task_status' => 'PROCESSING',
            'requires_approval' => false,
        ]);

    $refreshed = $this->task->fresh();
    expect($refreshed->status)->toBe('PROCESSING')
        ->and($refreshed->result_json['current_step'])->toBe(2)
        ->and($refreshed->result_json['node'])->toBe('reasoning')
        ->and($refreshed->result_json['cognitive_steps'])->toHaveCount(2);
});

test('POST /api/v1/internal/executions/{id}/status-update updates task to COMPLETED with duration and output', function () {
    $path = "/api/v1/internal/executions/{$this->executionId}/status-update";
    $payload = [
        'execution_id' => $this->executionId,
        'thread_id' => $this->threadId,
        'status' => 'completed',
        'response' => 'Reporte asíncrono generado correctamente.',
        'data' => ['artifacts' => ['report.pdf']],
        'completed_at' => now()->toIso8601String(),
    ];
    $headers = ($this->makeHmacHeaders)($path, $payload);

    $response = $this->withHeaders($headers)->postJson($path, $payload);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'execution_id' => $this->executionId,
            'task_status' => 'COMPLETED',
        ]);

    $refreshed = $this->task->fresh();
    expect($refreshed->status)->toBe('COMPLETED')
        ->and($refreshed->completed_at)->not->toBeNull()
        ->and($refreshed->duration)->not->toBeNull()
        ->and($refreshed->result_json['response'])->toBe('Reporte asíncrono generado correctamente.');
});

test('POST /api/v1/internal/executions/{id}/status-update registers HITL approval when waiting_approval received', function () {
    $path = "/api/v1/internal/executions/{$this->executionId}/status-update";
    $payload = [
        'execution_id' => $this->executionId,
        'thread_id' => $this->threadId,
        'status' => 'waiting_approval',
        'requires_approval' => true,
        'pending_approval_id' => 'approval-req-001',
        'prompt' => 'Modificar regla de firewall en producción',
        'data' => [
            'risk_assessment' => ['risk_level' => 'HIGH'],
            'plan' => ['command' => 'iptables -A INPUT ...'],
            'compensation_commands' => ['iptables -D INPUT ...'],
        ],
    ];
    $headers = ($this->makeHmacHeaders)($path, $payload);

    $response = $this->withHeaders($headers)->postJson($path, $payload);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'execution_id' => $this->executionId,
            'task_status' => 'WAITING_APPROVAL',
            'requires_approval' => true,
        ]);

    $refreshed = $this->task->fresh();
    expect($refreshed->status)->toBe('WAITING_APPROVAL');

    $approval = ApprovalRequest::where('thread_id', $this->threadId)->first();
    expect($approval)->not->toBeNull()
        ->and($approval->status)->toBe('PENDING')
        ->and($approval->risk_level)->toBe('HIGH')
        ->and($approval->proposed_plan_json['prompt'])->toBe('Modificar regla de firewall en producción');
});

test('unversioned route /api/internal/executions/{id}/status-update returns 404 not found (CWE-668)', function () {
    $path = "/api/internal/executions/{$this->executionId}/status-update";
    $payload = ['status' => 'in_progress'];
    $headers = ($this->makeHmacHeaders)($path, $payload);

    $this->withHeaders($headers)
        ->postJson($path, $payload)
        ->assertStatus(404);
});
