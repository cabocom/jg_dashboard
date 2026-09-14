<?php

use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
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

    $this->activeAgent = Agent::create([
        'agent_id' => 'agent-approve-01',
        'name' => 'Agent Approve Worker',
        'status' => 'ACTIVE',
        'metadata_json' => ['api_url' => 'http://test-agent:8000'],
    ]);
});

test('unauthenticated request to POST /api/v1/approvals/{id}/approve returns 401 unauthorized', function () {
    $response = $this->postJson('/api/v1/approvals/999/approve');

    $response->assertStatus(401);
});

test('viewer role receives 403 forbidden on POST /api/v1/approvals/{id}/approve', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
        ->postJson('/api/v1/approvals/999/approve');

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'Forbidden',
            'message' => 'Acceso denegado: rol insuficiente',
        ]);
});

test('returns 404 when approval_id does not exist', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson('/api/v1/approvals/99999/approve');

    $response->assertStatus(404)
        ->assertJsonPath('error', 'ApprovalFailed');
});

test('returns 409 conflict when approval is already resolved', function () {
    $approval = ApprovalRequest::create([
        'thread_id' => 'th-resolved-test',
        'agent_id' => $this->activeAgent->agent_id,
        'status' => 'APPROVED',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['step' => 'already executed'],
        'resolved_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson("/api/v1/approvals/{$approval->id}/approve");

    $response->assertStatus(409)
        ->assertJsonPath('error', 'ApprovalFailed');
});

test('returns 400 when associated agent is in PENDING status', function () {
    $pendingAgent = Agent::create([
        'agent_id' => 'agent-pending-01',
        'name' => 'Pending Agent',
        'status' => 'PENDING',
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'th-pending-agent',
        'agent_id' => $pendingAgent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['step' => 'action on pending agent'],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson("/api/v1/approvals/{$approval->id}/approve");

    $response->assertStatus(400)
        ->assertJsonPath('error', 'ApprovalFailed');
});

test('operator role approves pending execution and delegates command to jg_agente', function () {
    Http::fake([
        'http://test-agent:8000/executions/thread-hitl-approve-100/approve' => Http::response([
            'status' => 'resumed',
            'thread_id' => 'thread-hitl-approve-100',
            'checkpoint_id' => 'chk-resumed-01',
            'message' => 'Grafo cognitivo reanudado tras aprobación humana.',
        ], 200),
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'thread-hitl-approve-100',
        'agent_id' => $this->activeAgent->agent_id,
        'task_id' => 'task-100',
        'status' => 'PENDING',
        'risk_level' => 'CRITICAL',
        'proposed_plan_json' => [
            'summary' => 'Actualizar esquema de producción',
            'tools_involved' => ['DatabaseMigrator'],
        ],
        'compensation_commands_json' => [
            'rollback' => 'migrator rollback',
        ],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson("/api/v1/approvals/{$approval->id}/approve", [
            'justification' => 'Verificado con el equipo de DevOps y aprobado para proceder.',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'approved')
        ->assertJsonPath('data.approval.status', 'APPROVED')
        ->assertJsonPath('data.approval.thread_id', 'thread-hitl-approve-100')
        ->assertJsonPath('data.agent_response.status', 'resumed');

    // Verify agent received request with signed headers and correct payload
    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://test-agent:8000/executions/thread-hitl-approve-100/approve'
            && $request['thread_id'] === 'thread-hitl-approve-100'
            && $request['approved'] === true
            && $request['justification'] === 'Verificado con el equipo de DevOps y aprobado para proceder.'
            && $request->hasHeader('X-Signature')
            && $request->hasHeader('X-Control-Plane');
    });

    // Verify database was updated
    $fresh = $approval->fresh();
    expect($fresh->status)->toBe('APPROVED')
        ->and($fresh->user_id)->toBe($this->operatorUser->id)
        ->and($fresh->justification)->toBe('Verificado con el equipo de DevOps y aprobado para proceder.')
        ->and($fresh->resolved_at)->not->toBeNull();

    // Verify audit event was recorded
    $audit = AuditEvent::where('event_type', 'hitl.approved')->latest()->first();
    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($this->operatorUser->id)
        ->and($audit->agent_id)->toBe($this->activeAgent->agent_id)
        ->and($audit->payload_json['thread_id'])->toBe('thread-hitl-approve-100')
        ->and($audit->payload_json['justification'])->toBe('Verificado con el equipo de DevOps y aprobado para proceder.')
        ->and($audit->payload_json['risk_level'])->toBe('CRITICAL');
});

test('admin role can approve pending execution through role hierarchy', function () {
    Http::fake([
        'http://test-agent:8000/executions/thread-admin-approve/approve' => Http::response([
            'status' => 'resumed',
            'thread_id' => 'thread-admin-approve',
        ], 200),
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'thread-admin-approve',
        'agent_id' => $this->activeAgent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'deploy service'],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson("/api/v1/approvals/{$approval->id}/approve", [
            'justification' => 'Admin override approval',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.approval.status', 'APPROVED');

    expect($approval->fresh()->user_id)->toBe($this->adminUser->id);
});

test('handles network failure or agent error and leaves approval in PENDING status', function () {
    Http::fake([
        'http://test-agent:8000/executions/thread-failure/approve' => Http::response([
            'detail' => 'Internal Engine Graph Error',
        ], 500),
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'thread-failure',
        'agent_id' => $this->activeAgent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'deploy error'],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson("/api/v1/approvals/{$approval->id}/approve");

    $response->assertStatus(500)
        ->assertJsonPath('error', 'ApprovalFailed');

    // Verify approval remained PENDING so it can be retried safely
    expect($approval->fresh()->status)->toBe('PENDING')
        ->and($approval->fresh()->resolved_at)->toBeNull();
});

test('unversioned route /api/approvals/{id}/approve returns 404 not found (CWE-668)', function () {
    $approval = ApprovalRequest::create([
        'thread_id' => 'thread-alias-approve',
        'agent_id' => $this->activeAgent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'alias approve test'],
    ]);

    $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson("/api/approvals/{$approval->id}/approve", [
            'justification' => 'Aprobado mediante alias',
        ])
        ->assertStatus(404);
});
