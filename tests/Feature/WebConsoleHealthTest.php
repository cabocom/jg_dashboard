<?php

use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->operatorUser = User::create([
        'name' => 'Console Health Operator',
        'email' => 'operator.health@example.com',
        'password' => Hash::make('secret123'),
        'role' => 'operator',
    ]);

    $this->actingAs($this->operatorUser);
});

test('GET /console/agents returns ACTIVE status and is_healthy true when heartbeat is recent', function () {
    $agent = Agent::create([
        'agent_id' => 'agent-healthy-01',
        'name' => 'Agent Healthy Node',
        'status' => 'ACTIVE',
        'version' => '1.0.0',
        'last_heartbeat_at' => now()->subSeconds(30),
    ]);

    $response = $this->getJson('/console/agents');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.agent_id', 'agent-healthy-01')
        ->assertJsonPath('data.0.status', 'ACTIVE')
        ->assertJsonPath('data.0.is_healthy', true)
        ->assertJsonPath('summary.total', 1)
        ->assertJsonPath('summary.active', 1)
        ->assertJsonPath('summary.offline', 0);
});

test('GET /console/agents returns OFFLINE status and is_healthy false when heartbeat is older than 90s', function () {
    $agent = Agent::create([
        'agent_id' => 'agent-stale-01',
        'name' => 'Agent Stale Node',
        'status' => 'ACTIVE',
        'version' => '1.0.0',
        'last_heartbeat_at' => now()->subSeconds(95),
    ]);

    $response = $this->getJson('/console/agents');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.agent_id', 'agent-stale-01')
        ->assertJsonPath('data.0.status', 'OFFLINE')
        ->assertJsonPath('data.0.is_healthy', false)
        ->assertJsonPath('summary.total', 1)
        ->assertJsonPath('summary.active', 0)
        ->assertJsonPath('summary.offline', 1);
});

test('GET /console/agents returns OFFLINE status and is_healthy false when last_heartbeat_at is null', function () {
    $agent = Agent::create([
        'agent_id' => 'agent-no-heartbeat-01',
        'name' => 'Agent No Heartbeat',
        'status' => 'ACTIVE',
        'version' => '1.0.0',
        'last_heartbeat_at' => null,
    ]);

    $response = $this->getJson('/console/agents');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.agent_id', 'agent-no-heartbeat-01')
        ->assertJsonPath('data.0.status', 'OFFLINE')
        ->assertJsonPath('data.0.is_healthy', false)
        ->assertJsonPath('summary.total', 1)
        ->assertJsonPath('summary.active', 0)
        ->assertJsonPath('summary.offline', 1);
});

test('GET /console/agents preserves OFFLINE status for explicitly offline agents', function () {
    $agent = Agent::create([
        'agent_id' => 'agent-offline-explicit',
        'name' => 'Agent Explicitly Offline',
        'status' => 'OFFLINE',
        'version' => '1.0.0',
        'last_heartbeat_at' => now()->subHours(5),
    ]);

    $response = $this->getJson('/console/agents');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.agent_id', 'agent-offline-explicit')
        ->assertJsonPath('data.0.status', 'OFFLINE')
        ->assertJsonPath('data.0.is_healthy', false)
        ->assertJsonPath('summary.total', 1)
        ->assertJsonPath('summary.active', 0)
        ->assertJsonPath('summary.offline', 1);
});

test('GET /console/agents preserves PENDING status and sets is_healthy false', function () {
    $agent = Agent::create([
        'agent_id' => 'agent-pending-01',
        'name' => 'Agent Pending Bootstrap',
        'status' => 'PENDING',
        'version' => '1.0.0',
    ]);

    $response = $this->getJson('/console/agents');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.agent_id', 'agent-pending-01')
        ->assertJsonPath('data.0.status', 'PENDING')
        ->assertJsonPath('data.0.is_healthy', false)
        ->assertJsonPath('summary.total', 1)
        ->assertJsonPath('summary.active', 0)
        ->assertJsonPath('summary.offline', 0);
});

test('GET /console/agents aggregates summary metrics accurately for mixed cluster', function () {
    // 1. Healthy active agent (last heartbeat 15s ago)
    Agent::create([
        'agent_id' => 'agent-mix-01',
        'name' => 'Agent Cluster Alpha',
        'status' => 'ACTIVE',
        'last_heartbeat_at' => now()->subSeconds(15),
    ]);

    // 2. Dead active agent (last heartbeat 300s ago) -> should figure as OFFLINE
    Agent::create([
        'agent_id' => 'agent-mix-02',
        'name' => 'Agent Cluster Beta',
        'status' => 'ACTIVE',
        'last_heartbeat_at' => now()->subSeconds(300),
    ]);

    // 3. Stale active agent without heartbeat -> should figure as OFFLINE
    Agent::create([
        'agent_id' => 'agent-mix-03',
        'name' => 'Agent Cluster Gamma',
        'status' => 'ACTIVE',
        'last_heartbeat_at' => null,
    ]);

    // 4. Explicit OFFLINE agent
    Agent::create([
        'agent_id' => 'agent-mix-04',
        'name' => 'Agent Cluster Delta',
        'status' => 'OFFLINE',
    ]);

    // 5. Explicit PENDING agent
    Agent::create([
        'agent_id' => 'agent-mix-05',
        'name' => 'Agent Cluster Epsilon',
        'status' => 'PENDING',
    ]);

    $response = $this->getJson('/console/agents');

    $response->assertStatus(200);

    // Summary should report only 1 active and 3 offline
    expect($response->json('summary.total'))->toBe(5);
    expect($response->json('summary.active'))->toBe(1);
    expect($response->json('summary.offline'))->toBe(3);

    // Initial console view /console should also preload exact same counts
    $viewResponse = $this->get('/console');
    $viewResponse->assertStatus(200);
    expect($viewResponse->viewData('totalAgents'))->toBe(5);
    expect($viewResponse->viewData('activeAgents'))->toBe(1);
    expect($viewResponse->viewData('offlineAgents'))->toBe(3);
});
