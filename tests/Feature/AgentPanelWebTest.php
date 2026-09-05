<?php

use App\Models\Agent;
use App\Models\AuditEvent;
use App\Models\BootstrapToken;

test('GET /console renders the agent panel toolbar, containers, and provisioning modal', function () {
    $response = $this->get('/console');

    $response->assertStatus(200)
        ->assertSee('id="agents-search"', false)
        ->assertSee('id="agents-filter-status"', false)
        ->assertSee('id="btn-create-agent"', false)
        ->assertSee('data-view="cards"', false)
        ->assertSee('data-view="table"', false)
        ->assertSee('id="agents-cards-container"', false)
        ->assertSee('id="agents-table-container"', false)
        ->assertSee('id="modal-create-agent"', false)
        ->assertSee('id="form-create-agent"', false)
        ->assertSee('id="create-agent-result-box"', false)
        ->assertSee('id="btn-copy-token"', false);
});

test('POST /console/agents provisions a new agent and returns one-time bootstrap token', function () {
    $payload = [
        'name' => 'Agent SRE Worker 01',
        'version' => '1.2.0',
        'ttl_hours' => 24,
    ];

    $response = $this->postJson('/console/agents', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'agent_id',
            'name',
            'status',
            'bootstrap_token',
            'expires_at',
            'ttl_hours',
            'created_at',
        ])
        ->assertJson([
            'name' => 'Agent SRE Worker 01',
            'status' => 'PENDING',
            'ttl_hours' => 24,
        ]);

    $agentId = $response->json('agent_id');
    $token = $response->json('bootstrap_token');

    // Plaintext token is a 48-character random string
    expect(strlen($token))->toBe(48);

    // Verify Agent was persisted in database
    $agent = Agent::where('agent_id', $agentId)->first();
    expect($agent)->not->toBeNull();
    expect($agent->name)->toBe('Agent SRE Worker 01');
    expect($agent->status)->toBe('PENDING');
    expect($agent->version)->toBe('1.2.0');

    // Verify BootstrapToken record exists with hashed token
    $tokenRecord = BootstrapToken::where('agent_id', $agentId)->first();
    expect($tokenRecord)->not->toBeNull();
    expect($tokenRecord->token_hash)->not->toBe($token);
    expect($tokenRecord->token_hash)->toBe(hash('sha256', $token));

    // Verify AuditEvent was logged
    $audit = AuditEvent::where('event_type', 'AGENT_CREATED')->latest()->first();
    expect($audit)->not->toBeNull();
    expect($audit->agent_id)->toBe($agentId);
    expect($audit->payload_json['agent_id'])->toBe($agentId);
});

test('POST /console/agents validates required agent name', function () {
    $response = $this->postJson('/console/agents', [
        'name' => '',
        'version' => '1.0.0',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});
