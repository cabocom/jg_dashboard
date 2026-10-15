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
use App\Models\BootstrapToken;
use App\Services\Agents\AgentRegistrationService;

beforeEach(function () {
    $this->registrationService = app(AgentRegistrationService::class);
});

test('POST /api/v1/agents/register returns 401 when bootstrap token is missing', function () {
    $response = $this->postJson('/api/v1/agents/register', [
        'version' => '1.0.0',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Bootstrap token no provisto',
        ]);
});

test('agent registers successfully using Authorization Bearer header', function () {
    // 1. Create a pending agent
    $creation = $this->registrationService->createAgent('devops-agent-01', ttlHours: 24);
    $agentId = $creation['agent_id'];
    $plainToken = $creation['bootstrap_token'];

    // 2. jg_agente boots up and presents token via Bearer header
    $response = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson('/api/v1/agents/register', [
            'version' => '1.5.0',
            'capabilities' => ['shell:exec', 'git:push'],
            'metadata' => ['pod_name' => 'jg-agente-7d84f9b8c-x9z2p'],
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'message',
            'agent_id',
            'name',
            'status',
            'permanent_secret',
            'registered_at',
        ]);

    $permanentSecret = $response->json('permanent_secret');

    expect($response->json('status'))->toBe('ACTIVE')
        ->and($response->json('agent_id'))->toBe($agentId)
        ->and($response->json('name'))->toBe('devops-agent-01')
        ->and($permanentSecret)->toStartWith('sec_')
        ->and(strlen($permanentSecret))->toBeGreaterThan(40);

    // 3. Verify Agent in database is ACTIVE with permanent_secret_hash
    $agent = Agent::find($agentId);
    expect($agent->status)->toBe('ACTIVE')
        ->and($agent->version)->toBe('1.5.0')
        ->and($agent->capabilities_json)->toBe(['shell:exec', 'git:push'])
        ->and($agent->metadata_json['pod_name'])->toBe('jg-agente-7d84f9b8c-x9z2p');

    $expectedSecretHash = hash('sha256', $permanentSecret);
    expect($agent->getRawOriginal('permanent_secret_hash'))->toBe($expectedSecretHash);

    // 4. Verify bootstrap token is consumed in database
    $tokenRecord = BootstrapToken::where('agent_id', $agentId)->first();
    expect($tokenRecord->consumed)->toBeTrue()
        ->and($tokenRecord->consumed_at)->not->toBeNull();

    // 5. Verify audit event was logged
    $audit = AuditEvent::where('agent_id', $agentId)
        ->where('event_type', 'AGENT_ACTIVATED')
        ->first();
    expect($audit)->not->toBeNull();
});

test('agent registers successfully using bootstrap_token in request body', function () {
    $creation = $this->registrationService->createAgent('body-token-agent', ttlHours: 24);
    $agentId = $creation['agent_id'];
    $plainToken = $creation['bootstrap_token'];

    $response = $this->postJson('/api/v1/agents/register', [
        'bootstrap_token' => $plainToken,
        'version' => '1.0.0',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'agent_id' => $agentId,
            'name' => 'body-token-agent',
            'status' => 'ACTIVE',
        ]);

    $permanentSecret = $response->json('permanent_secret');
    expect($permanentSecret)->toStartWith('sec_');
});

test('subsequent registration attempt with already consumed token returns 401 unauthorized', function () {
    $creation = $this->registrationService->createAgent('one-time-agent', ttlHours: 24);
    $plainToken = $creation['bootstrap_token'];

    // First call succeeds
    $firstResponse = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson('/api/v1/agents/register');
    $firstResponse->assertStatus(200);

    // Second call with the same consumed token fails with 401 Unauthorized
    $secondResponse = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson('/api/v1/agents/register');

    $secondResponse->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Bootstrap token ya consumido.',
        ]);
});

test('registration attempt with expired bootstrap token (>24h) returns 401 unauthorized', function () {
    $creation = $this->registrationService->createAgent('expired-token-agent', ttlHours: -2);
    $plainToken = $creation['bootstrap_token'];

    $response = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson('/api/v1/agents/register');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Bootstrap token expirado (>24h).',
        ]);
});

test('registration attempt with non-existent token returns 401 unauthorized', function () {
    $response = $this->withHeader('Authorization', 'Bearer invalid_and_unknown_token_xyz')
        ->postJson('/api/v1/agents/register');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Bootstrap token no válido o inexistente.',
        ]);
});

test('registration attempt with revoked token returns 401 unauthorized', function () {
    $creation = $this->registrationService->createAgent('revoked-test-agent', ttlHours: 24);
    $agentId = $creation['agent_id'];
    $plainToken = $creation['bootstrap_token'];

    // Revoke token
    $this->registrationService->revokeBootstrapTokens($agentId);

    // Attempt registration
    $response = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson('/api/v1/agents/register');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Bootstrap token expirado (>24h).',
        ]);
});

test('unversioned route /api/agents/register returns 404 not found (CWE-668)', function () {
    $creation = $this->registrationService->createAgent('alias-test-agent', ttlHours: 24);
    $plainToken = $creation['bootstrap_token'];

    $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->postJson('/api/agents/register')
        ->assertStatus(404);
});

