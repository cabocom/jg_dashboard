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
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    Http::fake();
    Config::set('agent.base_url', 'http://test-agent:8000');
    Config::set('agent.internal_secret', 'internal_secret_test');
    Config::set('agent.timeout', 2);
    Config::set('agent.retry_times', 0);

    $this->jwtService = app(JwtService::class);

    $this->adminUser = User::create([
        'name' => 'Admin User',
        'email' => 'admin@test.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
    ]);

    $this->operatorUser = User::create([
        'name' => 'Operator User',
        'email' => 'operator@test.com',
        'password' => Hash::make('password123'),
        'role' => 'operator',
    ]);

    $this->viewerUser = User::create([
        'name' => 'Viewer User',
        'email' => 'viewer@test.com',
        'password' => Hash::make('password123'),
        'role' => 'viewer',
    ]);

    $this->adminToken = $this->jwtService->generateToken($this->adminUser);
    $this->operatorToken = $this->jwtService->generateToken($this->operatorUser);
    $this->viewerToken = $this->jwtService->generateToken($this->viewerUser);

    $this->agent1 = Agent::create([
        'agent_id' => 'agent-hitl-alpha',
        'name' => 'Agent Alpha',
        'status' => 'ACTIVE',
    ]);

    $this->agent2 = Agent::create([
        'agent_id' => 'agent-hitl-beta',
        'name' => 'Agent Beta',
        'status' => 'ACTIVE',
    ]);
});

test('unauthenticated request to GET /api/v1/approvals/pending returns 401 unauthorized', function () {
    $response = $this->getJson('/api/v1/approvals/pending');

    $response->assertStatus(401);
});

test('viewer role receives 403 forbidden on GET /api/v1/approvals/pending', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
        ->getJson('/api/v1/approvals/pending');

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'Forbidden',
            'message' => 'Acceso denegado: rol insuficiente',
        ]);
});

test('operator role can query pending approvals and receives 200 OK with formatted data', function () {
    $pastTime = now()->subMinutes(12);

    $req = ApprovalRequest::create([
        'thread_id' => 'thread-langgraph-pending-01',
        'agent_id' => $this->agent1->agent_id,
        'task_id' => 'task-001',
        'status' => 'PENDING',
        'risk_level' => 'CRITICAL',
        'proposed_plan_json' => [
            'summary' => 'Actualización crítica de base de datos en producción',
            'tools_involved' => ['DockerRunner', 'PostgresRunner'],
            'command' => 'docker exec -it postgres drop table users',
        ],
        'compensation_commands_json' => [
            'rollback' => 'docker exec -it postgres pg_restore backup.dump',
        ],
    ]);
    // Force created_at to past time
    $req->created_at = $pastTime;
    $req->save();

    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->getJson('/api/v1/approvals/pending');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'thread_id',
                    'task_id',
                    'agent_id',
                    'agent_name',
                    'status',
                    'risk_level',
                    'action_summary',
                    'tools_involved',
                    'proposed_plan',
                    'compensation_commands',
                    'created_at',
                    'elapsed_time',
                    'elapsed_seconds',
                ],
            ],
            'summary' => [
                'total_pending',
                'critical_count',
                'high_count',
                'other_count',
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);

    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['thread_id'])->toBe('thread-langgraph-pending-01')
        ->and($data[0]['agent_id'])->toBe($this->agent1->agent_id)
        ->and($data[0]['agent_name'])->toBe('Agent Alpha')
        ->and($data[0]['risk_level'])->toBe('CRITICAL')
        ->and($data[0]['action_summary'])->toBe('Actualización crítica de base de datos en producción')
        ->and($data[0]['tools_involved'])->toBe(['DockerRunner', 'PostgresRunner'])
        ->and($data[0]['elapsed_seconds'])->toBeGreaterThanOrEqual(700)
        ->and($response->json('summary.total_pending'))->toBe(1)
        ->and($response->json('summary.critical_count'))->toBe(1)
        ->and($response->json('summary.high_count'))->toBe(0);
});

test('admin role can query pending approvals through role hierarchy', function () {
    ApprovalRequest::create([
        'thread_id' => 'thread-admin-test',
        'agent_id' => $this->agent1->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['step' => 'restart nginx service'],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->getJson('/api/v1/approvals/pending');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.thread_id', 'thread-admin-test')
        ->assertJsonPath('data.0.action_summary', 'Paso del plan: restart nginx service');
});

test('endpoint filters out resolved requests and returns only pending', function () {
    ApprovalRequest::create([
        'thread_id' => 'th-pending',
        'agent_id' => $this->agent1->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'pending action'],
    ]);

    ApprovalRequest::create([
        'thread_id' => 'th-approved',
        'agent_id' => $this->agent1->agent_id,
        'status' => 'APPROVED',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'approved action'],
        'resolved_at' => now(),
    ]);

    ApprovalRequest::create([
        'thread_id' => 'th-rejected',
        'agent_id' => $this->agent1->agent_id,
        'status' => 'REJECTED',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'rejected action'],
        'resolved_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->getJson('/api/v1/approvals/pending');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['thread_id'])->toBe('th-pending');
});

test('endpoint filters by agent_id correctly', function () {
    ApprovalRequest::create([
        'thread_id' => 'th-alpha',
        'agent_id' => $this->agent1->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'alpha action'],
    ]);

    ApprovalRequest::create([
        'thread_id' => 'th-beta',
        'agent_id' => $this->agent2->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'CRITICAL',
        'proposed_plan_json' => ['action' => 'beta action'],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->getJson("/api/v1/approvals/pending?agent_id={$this->agent2->agent_id}");

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.thread_id', 'th-beta')
        ->assertJsonPath('data.0.agent_id', $this->agent2->agent_id)
        ->assertJsonPath('summary.total_pending', 1);
});

test('endpoint filters by risk_level correctly', function () {
    ApprovalRequest::create([
        'thread_id' => 'th-high-1',
        'agent_id' => $this->agent1->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'high action'],
    ]);

    ApprovalRequest::create([
        'thread_id' => 'th-critical-1',
        'agent_id' => $this->agent1->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'CRITICAL',
        'proposed_plan_json' => ['action' => 'critical action'],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->getJson('/api/v1/approvals/pending?risk_level=CRITICAL');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.thread_id', 'th-critical-1')
        ->assertJsonPath('data.0.risk_level', 'CRITICAL');
});

test('unversioned route /api/approvals/pending returns 404 not found (CWE-668)', function () {
    $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->getJson('/api/approvals/pending')
        ->assertStatus(404);
});
