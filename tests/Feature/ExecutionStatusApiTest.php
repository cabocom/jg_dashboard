<?php

use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    Config::set('agent.base_url', 'http://test-agent:8000');
    Config::set('agent.internal_secret', 'internal_secret_test');
    Config::set('agent.timeout', 5);
    Config::set('agent.retry_times', 0);

    $this->jwtService = app(JwtService::class);

    $this->adminUser = User::create([
        'name' => 'Admin User',
        'email' => 'admin_status@test.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
    ]);

    $this->operatorUser = User::create([
        'name' => 'Operator User',
        'email' => 'operator_status@test.com',
        'password' => Hash::make('password123'),
        'role' => 'operator',
    ]);

    $this->viewerUser = User::create([
        'name' => 'Viewer User',
        'email' => 'viewer_status@test.com',
        'password' => Hash::make('password123'),
        'role' => 'viewer',
    ]);

    $this->adminToken = $this->jwtService->generateToken($this->adminUser);
    $this->operatorToken = $this->jwtService->generateToken($this->operatorUser);
    $this->viewerToken = $this->jwtService->generateToken($this->viewerUser);

    $this->activeAgent = Agent::create([
        'agent_id' => (string) Str::uuid(),
        'name' => 'Agent-Exec-01',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', 'agent_sec_status_123'),
        'last_heartbeat_at' => now(),
        'metadata_json' => ['api_url' => 'http://test-agent:8000'],
    ]);
});

test('unauthenticated request to GET /api/v1/executions/{agent_id}/{thread_id} returns 401 unauthorized', function () {
    $response = $this->getJson("/api/v1/executions/{$this->activeAgent->agent_id}/thread_123");

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
        ]);
});

test('returns 404 when requested agent_id does not exist', function () {
    $nonExistentAgentId = (string) Str::uuid();

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->viewerToken)
        ->getJson("/api/v1/executions/{$nonExistentAgentId}/thread_123");

    $response->assertStatus(404)
        ->assertJson([
            'error' => 'Not Found',
        ]);
});

test('returns 400 when agent is in PENDING registration status', function () {
    $pendingAgent = Agent::create([
        'agent_id' => (string) Str::uuid(),
        'name' => 'Agent-Pending-01',
        'status' => 'PENDING',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->viewerToken)
        ->getJson("/api/v1/executions/{$pendingAgent->agent_id}/thread_123");

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Agent Pending Registration',
        ]);
});

test('returns 404 when thread_id is not found on the agent', function () {
    Http::fake([
        'http://test-agent:8000/executions/unknown_thread' => Http::response([
            'detail' => 'Hilo de ejecución no encontrado en el checkpointer.',
        ], 404),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->viewerToken)
        ->getJson("/api/v1/executions/{$this->activeAgent->agent_id}/unknown_thread");

    $response->assertStatus(404)
        ->assertJson([
            'error' => 'Execution Not Found',
            'agent_id' => $this->activeAgent->agent_id,
            'thread_id' => 'unknown_thread',
        ]);
});

test('viewer role can query execution status and receives 200 OK with unified state', function () {
    Http::fake([
        'http://test-agent:8000/executions/th_query_100' => Http::response([
            'execution_id' => 'exec_100',
            'thread_id' => 'th_query_100',
            'status' => 'completed',
            'prompt' => 'Verificar salud del sistema',
            'response' => 'Todo el sistema se encuentra saludable.',
            'current_step' => 3,
            'requires_approval' => false,
            'steps' => [
                ['name' => 'init', 'status' => 'completed'],
                ['name' => 'check_nodes', 'status' => 'completed'],
                ['name' => 'synthesize', 'status' => 'completed'],
            ],
        ], 200),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->viewerToken)
        ->getJson("/api/v1/executions/{$this->activeAgent->agent_id}/th_query_100");

    $response->assertStatus(200)
        ->assertJson([
            'agent_id' => $this->activeAgent->agent_id,
            'thread_id' => 'th_query_100',
            'execution_id' => 'exec_100',
            'status' => 'completed',
            'prompt' => 'Verificar salud del sistema',
            'output' => 'Todo el sistema se encuentra saludable.',
            'current_step' => 3,
            'requires_approval' => false,
        ]);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://test-agent:8000/executions/th_query_100' &&
            $request->method() === 'GET' &&
            $request->hasHeader('X-Control-Plane', 'jg_dashboard') &&
            $request->hasHeader('X-Signature');
    });
});

test('operator and admin roles can also access execution status endpoint', function () {
    Http::fake([
        'http://test-agent:8000/executions/th_query_operator' => Http::response([
            'execution_id' => 'exec_operator',
            'thread_id' => 'th_query_operator',
            'status' => 'in_progress',
        ], 200),
        'http://test-agent:8000/executions/th_query_admin' => Http::response([
            'execution_id' => 'exec_admin',
            'thread_id' => 'th_query_admin',
            'status' => 'in_progress',
        ], 200),
    ]);

    $respOperator = $this->withHeader('Authorization', 'Bearer ' . $this->operatorToken)
        ->getJson("/api/v1/executions/{$this->activeAgent->agent_id}/th_query_operator");
    $respOperator->assertStatus(200)->assertJson(['status' => 'in_progress']);

    $respAdmin = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
        ->getJson("/api/v1/executions/{$this->activeAgent->agent_id}/th_query_admin");
    $respAdmin->assertStatus(200)->assertJson(['status' => 'in_progress']);
});

test('unifies status with local ApprovalRequest if HITL checkpoint is present', function () {
    $threadId = 'th_hitl_checkpoint_99';

    ApprovalRequest::create([
        'thread_id' => $threadId,
        'agent_id' => $this->activeAgent->agent_id,
        'task_id' => 'exec_hitl_99',
        'status' => 'PENDING',
        'risk_level' => 'CRITICAL',
        'proposed_plan_json' => ['command' => 'format_disk'],
    ]);

    Http::fake([
        "http://test-agent:8000/executions/{$threadId}" => Http::response([
            'execution_id' => 'exec_hitl_99',
            'thread_id' => $threadId,
            'status' => 'waiting_approval',
            'requires_approval' => true,
            'plan' => ['command' => 'format_disk'],
        ], 200),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->operatorToken)
        ->getJson("/api/v1/executions/{$this->activeAgent->agent_id}/{$threadId}");

    $response->assertStatus(200)
        ->assertJson([
            'thread_id' => $threadId,
            'status' => 'waiting_approval',
            'requires_approval' => true,
            'approval_status' => 'PENDING',
            'hitl_governance' => [
                'status' => 'PENDING',
                'risk_level' => 'CRITICAL',
            ],
        ]);
});

test('handles network failure or unreachable agent with 502/503 and structured error', function () {
    Http::fake([
        'http://test-agent:8000/*' => function () {
            throw new ConnectionException('Failed to connect to jg_agente host');
        },
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->viewerToken)
        ->getJson("/api/v1/executions/{$this->activeAgent->agent_id}/th_unreachable");

    $response->assertStatus(503)
        ->assertJson([
            'error' => 'Agent Gateway Error',
            'agent_id' => $this->activeAgent->agent_id,
            'thread_id' => 'th_unreachable',
        ]);
});

test('unversioned route /api/executions/{agent_id}/{thread_id} returns 404 not found (CWE-668)', function () {
    $this->withHeader('Authorization', 'Bearer ' . $this->viewerToken)
        ->getJson("/api/executions/{$this->activeAgent->agent_id}/th_alias_test")
        ->assertStatus(404);
});
