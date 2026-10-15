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
use App\Models\AuditEvent;
use App\Services\Agents\AgentHealthMonitorService;

beforeEach(function () {
    $this->healthMonitor = app(AgentHealthMonitorService::class);
});

test('checkAndMarkOfflineAgents marks agents without heartbeat >90s as OFFLINE and records audit event', function () {
    // 1. Unresponsive active agent (95s ago)
    $staleAgent = Agent::create([
        'name' => 'stale-worker-01',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', 'secret'),
        'last_heartbeat_at' => now()->subSeconds(95),
    ]);

    // 2. Responsive active agent (20s ago)
    $healthyAgent = Agent::create([
        'name' => 'healthy-worker-01',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', 'secret'),
        'last_heartbeat_at' => now()->subSeconds(20),
    ]);

    // 3. Pending agent (unbootstrapped)
    $pendingAgent = Agent::create([
        'name' => 'pending-worker-01',
        'status' => 'PENDING',
        'last_heartbeat_at' => null,
    ]);

    $offline = $this->healthMonitor->checkAndMarkOfflineAgents(timeoutSeconds: 90);

    expect($offline)->toHaveCount(1)
        ->and($offline->first()->agent_id)->toBe($staleAgent->agent_id);

    // Stale agent is now OFFLINE
    expect($staleAgent->fresh()->status)->toBe('OFFLINE')
        ->and($staleAgent->fresh()->isOffline())->toBeTrue();

    // Healthy agent remains ACTIVE
    expect($healthyAgent->fresh()->status)->toBe('ACTIVE')
        ->and($healthyAgent->fresh()->isActive())->toBeTrue();

    // Pending agent remains PENDING
    expect($pendingAgent->fresh()->status)->toBe('PENDING');

    // Audit event recorded
    $audit = AuditEvent::where('agent_id', $staleAgent->agent_id)
        ->where('event_type', 'AGENT_OFFLINE')
        ->first();
    expect($audit)->not->toBeNull()
        ->and($audit->payload_json['timeout_seconds'])->toBe(90)
        ->and($audit->payload_json['previous_status'])->toBe('ACTIVE');
});

test('checkAndMarkOfflineAgents transitions active agents with null heartbeat to OFFLINE', function () {
    $nullHeartbeatAgent = Agent::create([
        'name' => 'active-null-heartbeat-agent',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', 'secret'),
        'last_heartbeat_at' => null,
    ]);

    $offline = $this->healthMonitor->checkAndMarkOfflineAgents(90);

    expect($offline)->toHaveCount(1)
        ->and($nullHeartbeatAgent->fresh()->status)->toBe('OFFLINE');
});

test('checkAndMarkOfflineAgents returns empty collection when all active agents are healthy', function () {
    Agent::create([
        'name' => 'quick-worker-alpha',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', 'secret'),
        'last_heartbeat_at' => now()->subSeconds(10),
    ]);

    $offline = $this->healthMonitor->checkAndMarkOfflineAgents(90);

    expect($offline)->toBeEmpty();
});
