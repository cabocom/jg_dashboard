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
use App\Services\Agents\AgentHeartbeatService;

beforeEach(function () {
    $this->heartbeatService = app(AgentHeartbeatService::class);
    $this->plainSecret = 'sec_' . bin2hex(random_bytes(24));
    $this->secretHash = hash('sha256', $this->plainSecret);

    $this->agent = Agent::create([
        'name' => 'telemetry-worker-01',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => $this->secretHash,
        'last_heartbeat_at' => now()->subMinutes(5),
    ]);
});

test('Agent verifyPermanentSecret correctly validates plain secret against SHA-256 hash', function () {
    expect($this->agent->verifyPermanentSecret($this->plainSecret))->toBeTrue()
        ->and($this->agent->verifyPermanentSecret('wrong_secret_123'))->toBeFalse();
});

test('processHeartbeat updates last_heartbeat_at, status to ACTIVE and records metrics', function () {
    $metricsPayload = [
        'cpu_percent' => 18.5,
        'memory_mb' => 312,
        'active_tasks' => 2,
    ];

    $result = $this->heartbeatService->processHeartbeat(
        agentId: $this->agent->agent_id,
        plainSecret: $this->plainSecret,
        status: 'ACTIVE',
        metrics: $metricsPayload
    );

    expect($result['agent_id'])->toBe($this->agent->agent_id)
        ->and($result['status'])->toBe('ACTIVE')
        ->and($result['metrics'])->toBe($metricsPayload);

    $refreshed = $this->agent->fresh();
    expect($refreshed->status)->toBe('ACTIVE')
        ->and($refreshed->metrics_json)->toBe($metricsPayload)
        ->and($refreshed->last_heartbeat_at->diffInSeconds(now()))->toBeLessThan(5);
});

test('processHeartbeat rejects invalid permanent secret with 401', function () {
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Credencial de agente inválida.');

    $this->heartbeatService->processHeartbeat(
        agentId: $this->agent->agent_id,
        plainSecret: 'invalid_secret_key'
    );
});

test('processHeartbeat rejects non-existent agent ID with 401', function () {
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Agente no encontrado o no registrado.');

    $this->heartbeatService->processHeartbeat(
        agentId: '00000000-0000-0000-0000-000000000999',
        plainSecret: $this->plainSecret
    );
});

test('processHeartbeat rejects agent in PENDING status with 401', function () {
    $pendingAgent = Agent::create([
        'name' => 'pending-agent-unbootstrapped',
        'status' => 'PENDING',
        'permanent_secret_hash' => $this->secretHash,
    ]);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('El agente no ha completado el proceso de registro.');

    $this->heartbeatService->processHeartbeat(
        agentId: $pendingAgent->agent_id,
        plainSecret: $this->plainSecret
    );
});
