<?php

use App\Models\Agent;

beforeEach(function () {
    $this->plainSecret = 'sec_' . bin2hex(random_bytes(24));
    $this->secretHash = hash('sha256', $this->plainSecret);

    $this->agent = Agent::create([
        'name' => 'telemetry-prod-agent',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => $this->secretHash,
        'last_heartbeat_at' => now()->subMinutes(2),
    ]);
});

test('POST /api/v1/agents/heartbeat returns 401 when agent_id or secret is missing', function () {
    // Missing all credentials
    $this->postJson('/api/v1/agents/heartbeat')
        ->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Identificador de agente o credencial permanente no provistos',
        ]);

    // Missing secret
    $this->withHeader('X-Agent-ID', $this->agent->agent_id)
        ->postJson('/api/v1/agents/heartbeat')
        ->assertStatus(401);
});

test('POST /api/v1/agents/heartbeat with X-Agent-ID header and Bearer token returns 200 OK and updates agent', function () {
    $metrics = [
        'cpu' => 14.2,
        'mem_mb' => 280,
    ];

    $response = $this->withHeaders([
        'X-Agent-ID' => $this->agent->agent_id,
        'Authorization' => "Bearer {$this->plainSecret}",
    ])->postJson('/api/v1/agents/heartbeat', [
        'status' => 'ACTIVE',
        'metrics' => $metrics,
        'active_executions' => 1,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Heartbeat registrado exitosamente',
            'agent_id' => $this->agent->agent_id,
            'name' => 'telemetry-prod-agent',
            'agent_status' => 'ACTIVE',
        ]);

    $refreshed = $this->agent->fresh();
    expect($refreshed->status)->toBe('ACTIVE')
        ->and($refreshed->metrics_json['cpu'])->toBe(14.2)
        ->and($refreshed->metrics_json['active_executions'])->toBe(1)
        ->and($refreshed->last_heartbeat_at->diffInSeconds(now()))->toBeLessThan(5);
});

test('POST /api/v1/agents/heartbeat with JSON body credentials returns 200 OK', function () {
    $response = $this->postJson('/api/v1/agents/heartbeat', [
        'agent_id' => $this->agent->agent_id,
        'permanent_secret' => $this->plainSecret,
        'metrics' => ['load_avg' => 0.45],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'agent_id' => $this->agent->agent_id,
        ]);
});

test('POST /api/v1/agents/heartbeat rejects invalid permanent secret with 401', function () {
    $response = $this->withHeaders([
        'X-Agent-ID' => $this->agent->agent_id,
        'Authorization' => 'Bearer wrong-secret-value',
    ])->postJson('/api/v1/agents/heartbeat');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Credencial de agente inválida.',
        ]);
});

test('POST /api/v1/agents/heartbeat rejects non-existent agent with 401', function () {
    $response = $this->withHeaders([
        'X-Agent-ID' => '00000000-0000-0000-0000-000000000999',
        'Authorization' => "Bearer {$this->plainSecret}",
    ])->postJson('/api/v1/agents/heartbeat');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Agente no encontrado o no registrado.',
        ]);
});

test('POST /api/v1/agents/heartbeat rejects agent in PENDING status with 401', function () {
    $pendingAgent = Agent::create([
        'name' => 'pending-agent-node',
        'status' => 'PENDING',
        'permanent_secret_hash' => $this->secretHash,
    ]);

    $response = $this->withHeaders([
        'X-Agent-ID' => $pendingAgent->agent_id,
        'Authorization' => "Bearer {$this->plainSecret}",
    ])->postJson('/api/v1/agents/heartbeat');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'El agente no ha completado el proceso de registro.',
        ]);
});

test('root alias /api/agents/heartbeat functions identically', function () {
    $response = $this->withHeaders([
        'X-Agent-ID' => $this->agent->agent_id,
        'Authorization' => "Bearer {$this->plainSecret}",
    ])->postJson('/api/agents/heartbeat');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'agent_id' => $this->agent->agent_id,
        ]);
});
