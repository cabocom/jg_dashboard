<?php

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\ApprovalRequest;

beforeEach(function () {
    $this->plainSecret = 'sec_' . bin2hex(random_bytes(24));
    $this->secretHash = hash('sha256', $this->plainSecret);

    $this->agent = Agent::create([
        'name' => 'async-webhook-agent',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => $this->secretHash,
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

test('fallback route /api/internal/executions/{id}/complete functions identically', function () {
    $response = $this->withHeaders([
        'X-Agent-ID' => $this->agent->agent_id,
        'Authorization' => "Bearer {$this->plainSecret}",
    ])->postJson("/api/internal/executions/{$this->executionId}/complete", [
        'execution_id' => $this->executionId,
        'thread_id' => $this->threadId,
        'status' => 'completed',
        'response' => 'Finalizado vía ruta fallback.',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'task_status' => 'COMPLETED',
        ]);
});
