<?php

use App\Models\Agent;
use App\Models\BootstrapToken;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->jwt = app(JwtService::class);

    $this->admin = User::create([
        'name' => 'Root Admin',
        'email' => 'admin_agent_api@example.com',
        'password' => Hash::make('secretpass123'),
        'role' => 'admin',
    ]);

    $this->operator = User::create([
        'name' => 'SRE Operator',
        'email' => 'operator_agent_api@example.com',
        'password' => Hash::make('secretpass123'),
        'role' => 'operator',
    ]);

    $this->viewer = User::create([
        'name' => 'ReadOnly Viewer',
        'email' => 'viewer_agent_api@example.com',
        'password' => Hash::make('secretpass123'),
        'role' => 'viewer',
    ]);

    $this->adminToken = $this->jwt->generateToken($this->admin);
    $this->operatorToken = $this->jwt->generateToken($this->operator);
    $this->viewerToken = $this->jwt->generateToken($this->viewer);
});

test('unauthenticated request to POST /api/v1/admin/agents returns 401 unauthorized', function () {
    $response = $this->postJson('/api/v1/admin/agents', [
        'name' => 'test-agent',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Token de autenticación no provisto',
        ]);
});

test('viewer role receives 403 forbidden on POST /api/v1/admin/agents', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
        ->postJson('/api/v1/admin/agents', [
            'name' => 'agent-by-viewer',
        ]);

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'Forbidden',
            'message' => 'Acceso denegado: rol insuficiente',
        ]);
});

test('operator role receives 403 forbidden on POST /api/v1/admin/agents', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson('/api/v1/admin/agents', [
            'name' => 'agent-by-operator',
        ]);

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'Forbidden',
            'message' => 'Acceso denegado: rol insuficiente',
        ]);
});

test('validation error returns 422 when agent name is missing or invalid', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson('/api/v1/admin/agents', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('admin role registers new agent and receives 201 with agent_id and bootstrap_token', function () {
    $payload = [
        'name' => 'production-worker-01',
        'version' => '1.2.0',
        'capabilities' => ['shell:exec', 'git:commit'],
        'metadata' => ['environment' => 'production', 'cloud' => 'aws'],
        'ttl_hours' => 48,
    ];

    $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson('/api/v1/admin/agents', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'agent_id',
            'name',
            'status',
            'bootstrap_token',
            'expires_at',
            'ttl_hours',
            'created_at',
        ]);

    $agentId = $response->json('agent_id');
    $tokenPlaintext = $response->json('bootstrap_token');

    expect($agentId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/')
        ->and(strlen($tokenPlaintext))->toBeGreaterThanOrEqual(32)
        ->and($response->json('name'))->toBe('production-worker-01')
        ->and($response->json('status'))->toBe('PENDING')
        ->and($response->json('ttl_hours'))->toBe(48);

    // Verify database state: agent is created with status PENDING
    $this->assertDatabaseHas('dashboard_agents', [
        'agent_id' => $agentId,
        'name' => 'production-worker-01',
        'status' => 'PENDING',
        'version' => '1.2.0',
    ]);

    // Verify database state: token is stored hashed and unconsumed
    $tokenHash = hash('sha256', $tokenPlaintext);
    $this->assertDatabaseHas('dashboard_bootstrap_tokens', [
        'agent_id' => $agentId,
        'token_hash' => $tokenHash,
        'consumed' => false,
    ]);

    // Verify audit event is logged
    $this->assertDatabaseHas('dashboard_audit_events', [
        'event_type' => 'AGENT_CREATED',
        'user_id' => $this->admin->id,
    ]);
});

test('admin can list registered agents via GET /api/v1/admin/agents', function () {
    Agent::create([
        'agent_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'list-agent-alpha',
        'status' => 'PENDING',
    ]);

    Agent::create([
        'agent_id' => '00000000-0000-0000-0000-000000000002',
        'name' => 'list-agent-beta',
        'status' => 'ACTIVE',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->getJson('/api/v1/admin/agents');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['agent_id', 'name', 'status', 'created_at'],
            ],
        ]);

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('list-agent-alpha', 'list-agent-beta');
});

test('non-admin roles cannot list agents via GET /api/v1/admin/agents', function () {
    $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->getJson('/api/v1/admin/agents')
        ->assertStatus(403);

    $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
        ->getJson('/api/v1/admin/agents')
        ->assertStatus(403);
});

test('admin can regenerate bootstrap token for a PENDING agent', function () {
    // 1. Create agent via POST
    $createResponse = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson('/api/v1/admin/agents', [
            'name' => 'agent-for-regen',
        ]);
    $createResponse->assertStatus(201);
    $agentId = $createResponse->json('agent_id');
    $firstToken = $createResponse->json('bootstrap_token');

    // 2. Regenerate token
    $regenResponse = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson("/api/v1/admin/agents/{$agentId}/regenerate-token", [
            'ttl_hours' => 12,
        ]);

    $regenResponse->assertStatus(200)
        ->assertJsonStructure([
            'agent_id',
            'name',
            'status',
            'bootstrap_token',
            'expires_at',
        ]);

    $newToken = $regenResponse->json('bootstrap_token');
    expect($newToken)->not->toBe($firstToken);

    // Old token should now be expired/invalidated
    $oldHash = hash('sha256', $firstToken);
    $oldTokenModel = BootstrapToken::where('token_hash', $oldHash)->first();
    expect($oldTokenModel->isExpired())->toBeTrue();

    // New token is active (valid: not consumed and not expired)
    $newHash = hash('sha256', $newToken);
    $newTokenModel = BootstrapToken::where('token_hash', $newHash)->first();
    expect($newTokenModel->isValid())->toBeTrue();
});

test('regenerate token returns 400 if agent is already ACTIVE', function () {
    $activeAgent = Agent::create([
        'agent_id' => '00000000-0000-0000-0000-000000000099',
        'name' => 'already-active-agent',
        'status' => 'ACTIVE',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson("/api/v1/admin/agents/{$activeAgent->agent_id}/regenerate-token");

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Bad Request',
        ]);
});

test('admin can revoke tokens via POST /api/v1/admin/agents/{id}/revoke-tokens', function () {
    $create = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson('/api/v1/admin/agents', ['name' => 'agent-for-admin-revoke']);
    $agentId = $create->json('agent_id');

    $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson("/api/v1/admin/agents/{$agentId}/revoke-tokens");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'revoked_count' => 1,
        ]);
});

test('operator and viewer cannot revoke tokens', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson('/api/v1/admin/agents/dummy-id/revoke-tokens');
    $response->assertStatus(403);

    $responseViewer = $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
        ->postJson('/api/v1/admin/agents/dummy-id/revoke-tokens');
    $responseViewer->assertStatus(403);
});

