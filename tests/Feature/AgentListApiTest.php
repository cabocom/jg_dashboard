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
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->jwt = app(JwtService::class);

    $this->viewer = User::create([
        'name' => 'ReadOnly Viewer',
        'email' => 'viewer_agent_list@example.com',
        'password' => Hash::make('secretpass123'),
        'role' => 'viewer',
    ]);

    $this->operator = User::create([
        'name' => 'Operator User',
        'email' => 'operator_agent_list@example.com',
        'password' => Hash::make('secretpass123'),
        'role' => 'operator',
    ]);

    $this->admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin_agent_list@example.com',
        'password' => Hash::make('secretpass123'),
        'role' => 'admin',
    ]);

    $this->viewerToken = $this->jwt->generateToken($this->viewer);
    $this->operatorToken = $this->jwt->generateToken($this->operator);
    $this->adminToken = $this->jwt->generateToken($this->admin);
});

test('unauthenticated request to GET /api/v1/agents returns 401 unauthorized', function () {
    $response = $this->getJson('/api/v1/agents');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Token de autenticación no provisto',
        ]);
});

test('authenticated user can query GET /api/v1/agents with pagination and aggregated summary metrics', function () {
    // 1. Create heterogeneous set of agents
    $activeAgent = Agent::create([
        'name' => 'worker-node-01',
        'status' => 'ACTIVE',
        'version' => '1.0.0',
        'permanent_secret_hash' => hash('sha256', 'secret1'),
        'last_heartbeat_at' => now()->subSeconds(20),
        'capabilities_json' => ['shell:exec'],
        'metrics_json' => ['cpu' => 12.5, 'mem_mb' => 256],
    ]);

    $offlineAgent = Agent::create([
        'name' => 'worker-node-02',
        'status' => 'OFFLINE',
        'version' => '0.9.0',
        'permanent_secret_hash' => hash('sha256', 'secret2'),
        'last_heartbeat_at' => now()->subHours(2),
    ]);

    $pendingAgent = Agent::create([
        'name' => 'worker-node-03',
        'status' => 'PENDING',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
        ->getJson('/api/v1/agents');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'agent_id',
                    'name',
                    'status',
                    'version',
                    'is_healthy',
                    'last_heartbeat_at',
                    'capabilities',
                    'metrics',
                    'created_at',
                ],
            ],
            'summary' => [
                'total',
                'active',
                'offline',
                'pending',
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);

    expect($response->json('summary.total'))->toBe(3)
        ->and($response->json('summary.active'))->toBe(1)
        ->and($response->json('summary.offline'))->toBe(1)
        ->and($response->json('summary.pending'))->toBe(1);

    // Verify secrets are NEVER leaked
    $rawResponse = $response->getContent();
    expect($rawResponse)->not->toContain('permanent_secret_hash')
        ->and($rawResponse)->not->toContain('secret1')
        ->and($rawResponse)->not->toContain('secret2');
});

test('operator and admin roles can also access GET /api/v1/agents', function () {
    $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->getJson('/api/v1/agents')
        ->assertStatus(200);

    $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->getJson('/api/v1/agents')
        ->assertStatus(200);
});

test('filter by status returns only agents with requested status', function () {
    Agent::create(['name' => 'active-a', 'status' => 'ACTIVE', 'last_heartbeat_at' => now()]);
    Agent::create(['name' => 'active-b', 'status' => 'ACTIVE', 'last_heartbeat_at' => now()]);
    Agent::create(['name' => 'offline-a', 'status' => 'OFFLINE']);

    $response = $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
        ->getJson('/api/v1/agents?status=ACTIVE');

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->toHaveCount(2);
    foreach ($data as $item) {
        expect($item['status'])->toBe('ACTIVE');
    }
});

test('search query filters agents by name or agent_id', function () {
    Agent::create(['name' => 'cluster-alpha-01', 'status' => 'ACTIVE']);
    Agent::create(['name' => 'cluster-beta-02', 'status' => 'ACTIVE']);

    $response = $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
        ->getJson('/api/v1/agents?search=alpha');

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('cluster-alpha-01');
});

test('GET /api/v1/agents/{agent_id} returns detailed single agent state and health', function () {
    $agent = Agent::create([
        'name' => 'single-detail-agent',
        'status' => 'ACTIVE',
        'version' => '2.0.0',
        'permanent_secret_hash' => hash('sha256', 'supersecret'),
        'last_heartbeat_at' => now()->subSeconds(10),
        'capabilities_json' => ['docker:ps', 'k8s:pods'],
        'metadata_json' => ['zone' => 'us-east-1a'],
        'metrics_json' => ['cpu' => 5.2],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
        ->getJson("/api/v1/agents/{$agent->agent_id}");

    $response->assertStatus(200)
        ->assertJson([
            'agent_id' => $agent->agent_id,
            'name' => 'single-detail-agent',
            'status' => 'ACTIVE',
            'version' => '2.0.0',
            'is_healthy' => true,
            'capabilities' => ['docker:ps', 'k8s:pods'],
            'metadata' => ['zone' => 'us-east-1a'],
            'metrics' => ['cpu' => 5.2],
        ]);

    expect($response->getContent())->not->toContain('permanent_secret_hash');
});

test('GET /api/v1/agents/{agent_id} returns 404 when agent does not exist', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
        ->getJson('/api/v1/agents/00000000-0000-0000-0000-000000000999');

    $response->assertStatus(404)
        ->assertJson([
            'error' => 'Not Found',
            'message' => 'Agente no encontrado.',
        ]);
});

test('unversioned route /api/agents returns 404 not found (CWE-668)', function () {
    $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
        ->getJson('/api/agents')
        ->assertStatus(404);
});
