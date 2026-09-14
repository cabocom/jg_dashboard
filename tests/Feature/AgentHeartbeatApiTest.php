<?php

use App\Models\Agent;

beforeEach(function () {
    $this->plainSecret = 'sec_' . bin2hex(random_bytes(24));
    $this->secretHash = hash('sha256', $this->plainSecret);

    $this->agent = Agent::create([
        'name' => 'telemetry-prod-agent',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => $this->secretHash,
        'permanent_secret_encrypted' => $this->plainSecret,
        'last_heartbeat_at' => now()->subMinutes(2),
    ]);
});

function createHmacHeaders(string $method, string $path, string $agentId, string $secret, array|string $body = '', ?int $timestamp = null): array
{
    $ts = $timestamp ?? time();
    $rawBody = is_array($body) ? json_encode($body, JSON_UNESCAPED_SLASHES) : $body;
    $bodyHash = hash('sha256', $rawBody);
    $normalizedPath = '/' . ltrim($path, '/');
    $normalizedMethod = strtoupper($method);

    $canonicalString = "{$normalizedMethod}\n{$normalizedPath}\n{$ts}\n{$bodyHash}";
    $signature = hash_hmac('sha256', $canonicalString, $secret);

    return [
        'X-Agent-ID' => $agentId,
        'X-Timestamp' => (string) $ts,
        'X-Signature' => $signature,
        'Accept' => 'application/json',
    ];
}

test('POST /api/v1/agents/heartbeat returns 401 when HMAC headers are missing', function () {
    // Missing all HMAC headers
    $this->postJson('/api/v1/agents/heartbeat')
        ->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Cabeceras de autenticación HMAC requeridas (X-Signature, X-Timestamp, X-Agent-ID)',
        ]);

    // Missing X-Signature
    $this->withHeaders([
        'X-Agent-ID' => $this->agent->agent_id,
        'X-Timestamp' => (string) time(),
    ])->postJson('/api/v1/agents/heartbeat')
        ->assertStatus(401);
});

test('POST /api/v1/agents/heartbeat rejects plaintext secret transmission without HMAC signature (CWE-319)', function () {
    // Legacy plaintext attempt with Bearer token or X-Agent-Secret must be rejected
    $this->withHeaders([
        'X-Agent-ID' => $this->agent->agent_id,
        'Authorization' => "Bearer {$this->plainSecret}",
        'X-Agent-Secret' => $this->plainSecret,
    ])->postJson('/api/v1/agents/heartbeat', [
        'status' => 'ACTIVE',
        'metrics' => ['cpu' => 10],
    ])->assertStatus(401)
      ->assertJson([
          'error' => 'Unauthorized',
          'message' => 'Cabeceras de autenticación HMAC requeridas (X-Signature, X-Timestamp, X-Agent-ID)',
      ]);
});

test('POST /api/v1/agents/heartbeat with valid HMAC signature returns 200 OK and updates agent', function () {
    $metrics = [
        'cpu' => 14.2,
        'mem_mb' => 280,
    ];
    $payload = [
        'status' => 'ACTIVE',
        'metrics' => $metrics,
        'active_executions' => 1,
    ];

    $headers = createHmacHeaders(
        method: 'POST',
        path: '/api/v1/agents/heartbeat',
        agentId: $this->agent->agent_id,
        secret: $this->plainSecret,
        body: $payload
    );

    $response = $this->withHeaders($headers)->postJson('/api/v1/agents/heartbeat', $payload);

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

test('POST /api/v1/agents/heartbeat rejects invalid HMAC signature with 401', function () {
    $payload = ['status' => 'ACTIVE'];
    $headers = createHmacHeaders(
        method: 'POST',
        path: '/api/v1/agents/heartbeat',
        agentId: $this->agent->agent_id,
        secret: 'wrong_secret_key_abcdef',
        body: $payload
    );

    $response = $this->withHeaders($headers)->postJson('/api/v1/agents/heartbeat', $payload);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Firma HMAC-SHA256 inválida.',
        ]);
});

test('POST /api/v1/agents/heartbeat rejects tampered request body with 401', function () {
    $originalPayload = ['status' => 'ACTIVE', 'metrics' => ['cpu' => 10]];
    $tamperedPayload = ['status' => 'ACTIVE', 'metrics' => ['cpu' => 99]];

    $headers = createHmacHeaders(
        method: 'POST',
        path: '/api/v1/agents/heartbeat',
        agentId: $this->agent->agent_id,
        secret: $this->plainSecret,
        body: $originalPayload
    );

    $response = $this->withHeaders($headers)->postJson('/api/v1/agents/heartbeat', $tamperedPayload);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Firma HMAC-SHA256 inválida.',
        ]);
});

test('POST /api/v1/agents/heartbeat rejects timestamp outside anti-replay window with 401', function () {
    $payload = ['status' => 'ACTIVE'];
    $expiredTimestamp = time() - 360; // 6 minutes ago (> 300s window)

    $headers = createHmacHeaders(
        method: 'POST',
        path: '/api/v1/agents/heartbeat',
        agentId: $this->agent->agent_id,
        secret: $this->plainSecret,
        body: $payload,
        timestamp: $expiredTimestamp
    );

    $response = $this->withHeaders($headers)->postJson('/api/v1/agents/heartbeat', $payload);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Timestamp fuera de la ventana de tolerancia temporal (300s)',
        ]);
});

test('POST /api/v1/agents/heartbeat rejects non-existent agent with 401', function () {
    $payload = ['status' => 'ACTIVE'];
    $headers = createHmacHeaders(
        method: 'POST',
        path: '/api/v1/agents/heartbeat',
        agentId: '00000000-0000-0000-0000-000000000999',
        secret: $this->plainSecret,
        body: $payload
    );

    $response = $this->withHeaders($headers)->postJson('/api/v1/agents/heartbeat', $payload);

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
        'permanent_secret_encrypted' => $this->plainSecret,
    ]);

    $payload = ['status' => 'ACTIVE'];
    $headers = createHmacHeaders(
        method: 'POST',
        path: '/api/v1/agents/heartbeat',
        agentId: $pendingAgent->agent_id,
        secret: $this->plainSecret,
        body: $payload
    );

    $response = $this->withHeaders($headers)->postJson('/api/v1/agents/heartbeat', $payload);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'El agente no ha completado el proceso de registro.',
        ]);
});

test('root alias /api/agents/heartbeat with valid HMAC signature functions identically', function () {
    $payload = ['status' => 'ACTIVE'];
    $headers = createHmacHeaders(
        method: 'POST',
        path: '/api/agents/heartbeat',
        agentId: $this->agent->agent_id,
        secret: $this->plainSecret,
        body: $payload
    );

    $response = $this->withHeaders($headers)->postJson('/api/agents/heartbeat', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'agent_id' => $this->agent->agent_id,
        ]);
});
