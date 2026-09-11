<?php

use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    Config::set('agent.base_url', 'http://test-agent:8000');
    Config::set('agent.internal_secret', 'internal_secret_test');
    Config::set('agent.timeout', 5);
    Config::set('agent.retry_times', 0);

    $this->admin = User::create([
        'name' => 'Root Admin',
        'email' => 'admin_console@example.com',
        'password' => Hash::make('secretpass'),
        'role' => 'admin',
    ]);

    $this->operator = User::create([
        'name' => 'SRE Operator',
        'email' => 'operator_console@example.com',
        'password' => Hash::make('secretpass'),
        'role' => 'operator',
    ]);

    $this->viewer = User::create([
        'name' => 'ReadOnly Viewer',
        'email' => 'viewer_console@example.com',
        'password' => Hash::make('secretpass'),
        'role' => 'viewer',
    ]);

    $this->agent = Agent::create([
        'agent_id' => 'agent-console-rbac-01',
        'name' => 'Agent Console RBAC',
        'status' => 'ACTIVE',
        'metadata_json' => ['api_url' => 'http://test-agent:8000'],
    ]);
});

test('unauthenticated request to console mutation endpoints returns 401 unauthorized', function () {
    $approval = ApprovalRequest::create([
        'thread_id' => 'th-unauth-1',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'test unauthenticated'],
    ]);

    $res1 = $this->postJson('/console/agents', ['name' => 'Agent Unauth']);
    $res1->assertStatus(401);

    $res2 = $this->postJson("/console/approvals/{$approval->id}/approve", ['justification' => 'test']);
    $res2->assertStatus(401);

    $res3 = $this->postJson("/console/approvals/{$approval->id}/reject", ['justification' => 'motivo rechazo']);
    $res3->assertStatus(401);

    $res4 = $this->postJson('/console/executions/trigger', [
        'agent_id' => $this->agent->agent_id,
        'prompt' => 'test prompt',
    ]);
    $res4->assertStatus(401);
});

test('viewer role receives 403 forbidden on all console mutation endpoints', function () {
    $approval = ApprovalRequest::create([
        'thread_id' => 'th-viewer-1',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'test viewer forbidden'],
    ]);

    $this->actingAs($this->viewer);

    $res1 = $this->postJson('/console/agents', ['name' => 'Agent Viewer Test']);
    $res1->assertStatus(403)
        ->assertJson([
            'error' => 'Forbidden',
        ]);

    $res2 = $this->postJson("/console/approvals/{$approval->id}/approve", ['justification' => 'intento de aprobacion']);
    $res2->assertStatus(403)
        ->assertJson([
            'error' => 'Forbidden',
        ]);

    $res3 = $this->postJson("/console/approvals/{$approval->id}/reject", ['justification' => 'intento de rechazo']);
    $res3->assertStatus(403)
        ->assertJson([
            'error' => 'Forbidden',
        ]);

    $res4 = $this->postJson('/console/executions/trigger', [
        'agent_id' => $this->agent->agent_id,
        'prompt' => 'intento de ejecucion',
    ]);
    $res4->assertStatus(403)
        ->assertJson([
            'error' => 'Forbidden',
        ]);
});

test('operator role receives 403 forbidden on agent provisioning', function () {
    $response = $this->actingAs($this->operator)->postJson('/console/agents', [
        'name' => 'Agent Operator Test',
        'version' => '1.0.0',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'Forbidden',
        ]);
});

test('operator role can trigger execution and resolve HITL approvals', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'status' => 'success',
            'thread_id' => (string) Str::uuid(),
            'output' => ['message' => 'Ejecutado'],
            'trace' => ['steps' => []],
        ], 200),
        'http://test-agent:8000/executions/th-op-approve/approve' => Http::response([
            'status' => 'resumed',
            'thread_id' => 'th-op-approve',
        ], 200),
        'http://test-agent:8000/executions/th-op-reject/reject' => Http::response([
            'status' => 'cancelled',
            'thread_id' => 'th-op-reject',
        ], 200),
    ]);

    $this->actingAs($this->operator);

    // 1. Trigger execution
    $triggerRes = $this->postJson('/console/executions/trigger', [
        'agent_id' => $this->agent->agent_id,
        'prompt' => 'Diagnosticar estado del cluster',
    ]);
    $triggerRes->assertStatus(200);

    // 2. Approve HITL
    $approval1 = ApprovalRequest::create([
        'thread_id' => 'th-op-approve',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'Plan a aprobar por operador'],
    ]);

    $approveRes = $this->postJson("/console/approvals/{$approval1->id}/approve", [
        'justification' => 'Aprobado por el operador en guardia',
    ]);
    $approveRes->assertStatus(200)->assertJsonPath('status', 'approved');

    // 3. Reject HITL
    $approval2 = ApprovalRequest::create([
        'thread_id' => 'th-op-reject',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'CRITICAL',
        'proposed_plan_json' => ['action' => 'Plan a rechazar por operador'],
    ]);

    $rejectRes = $this->postJson("/console/approvals/{$approval2->id}/reject", [
        'justification' => 'Rechazado por riesgo critico de parada',
    ]);
    $rejectRes->assertStatus(200)->assertJsonPath('status', 'rejected');
});

test('admin role can provision agents, trigger executions, and resolve HITL approvals', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'status' => 'success',
            'thread_id' => (string) Str::uuid(),
            'output' => ['message' => 'Ejecutado por admin'],
            'trace' => ['steps' => []],
        ], 200),
        'http://test-agent:8000/executions/th-adm-approve/approve' => Http::response([
            'status' => 'resumed',
            'thread_id' => 'th-adm-approve',
        ], 200),
    ]);

    $this->actingAs($this->admin);

    // 1. Provision agent
    $provRes = $this->postJson('/console/agents', [
        'name' => 'Agent Admin Provisioned',
        'version' => '1.0.0',
        'ttl_hours' => 48,
    ]);
    $provRes->assertStatus(201)
        ->assertJsonPath('status', 'PENDING');

    // 2. Trigger execution
    $triggerRes = $this->postJson('/console/executions/trigger', [
        'agent_id' => $this->agent->agent_id,
        'prompt' => 'Ejecucion disparada por admin',
    ]);
    $triggerRes->assertStatus(200);

    // 3. Approve HITL
    $approval = ApprovalRequest::create([
        'thread_id' => 'th-adm-approve',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'CRITICAL',
        'proposed_plan_json' => ['action' => 'Plan aprobado por admin'],
    ]);

    $approveRes = $this->postJson("/console/approvals/{$approval->id}/approve", [
        'justification' => 'Aprobado por el administrador del sistema',
    ]);
    $approveRes->assertStatus(200)->assertJsonPath('status', 'approved');
});

test('HITL resolution does not create dummy operator users and records authentic user id', function () {
    Http::fake([
        'http://test-agent:8000/executions/th-audit-verify/approve' => Http::response([
            'status' => 'resumed',
            'thread_id' => 'th-audit-verify',
        ], 200),
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'th-audit-verify',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'Verificar trazabilidad de usuario'],
    ]);

    $this->actingAs($this->operator);

    $response = $this->postJson("/console/approvals/{$approval->id}/approve", [
        'justification' => 'Verificacion de auditoria y no creacion de dummy',
    ]);
    $response->assertStatus(200);

    // Verify approval record was linked to real operator
    $approval->refresh();
    expect($approval->user_id)->toBe($this->operator->id);

    // Verify no dummy user was created
    expect(User::where('email', 'operator.console@example.com')->count())->toBe(0);

    // Verify audit event reflects operator
    $audit = AuditEvent::where('event_type', 'hitl.approved')
        ->latest()
        ->first();
    expect($audit)->not->toBeNull();
    expect($audit->user_id)->toBe($this->operator->id);
    expect($audit->payload_json['approval_id'])->toBe($approval->id);
});
