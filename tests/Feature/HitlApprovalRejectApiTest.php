<?php

use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

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
        'agent_id' => 'agent-reject-01',
        'name' => 'Agent Reject Worker',
        'status' => 'ACTIVE',
        'metadata_json' => ['api_url' => 'http://test-agent:8000'],
    ]);
});

test('unauthenticated request to POST /api/v1/approvals/{id}/reject returns 401 unauthorized', function () {
    $response = $this->postJson('/api/v1/approvals/999/reject', [
        'justification' => 'Motivo de rechazo de prueba',
    ]);

    $response->assertStatus(401);
});

test('viewer role receives 403 forbidden on POST /api/v1/approvals/{id}/reject', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
        ->postJson('/api/v1/approvals/999/reject', [
            'justification' => 'Motivo de rechazo de prueba',
        ]);

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'Forbidden',
            'message' => 'Acceso denegado: rol insuficiente',
        ]);
});

test('validation returns 422 when justification is missing or shorter than 5 characters', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson('/api/v1/approvals/999/reject', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['justification']);

    $responseShort = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson('/api/v1/approvals/999/reject', [
            'justification' => 'no',
        ]);

    $responseShort->assertStatus(422)
        ->assertJsonValidationErrors(['justification']);
});

test('returns 404 when approval_id does not exist', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson('/api/v1/approvals/99999/reject', [
            'justification' => 'Plan de prueba descartado por el operador',
        ]);

    $response->assertStatus(404)
        ->assertJsonPath('error', 'RejectionFailed');
});

test('returns 409 conflict when approval is already resolved', function () {
    $approval = ApprovalRequest::create([
        'thread_id' => 'th-already-rejected',
        'agent_id' => $this->activeAgent->agent_id,
        'status' => 'REJECTED',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['step' => 'already rejected action'],
        'resolved_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson("/api/v1/approvals/{$approval->id}/reject", [
            'justification' => 'Rechazando nuevamente por redundancia',
        ]);

    $response->assertStatus(409)
        ->assertJsonPath('error', 'RejectionFailed');
});

test('returns 400 when associated agent is in PENDING status', function () {
    $pendingAgent = Agent::create([
        'agent_id' => 'agent-pending-reject-01',
        'name' => 'Pending Agent',
        'status' => 'PENDING',
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'th-pending-agent-reject',
        'agent_id' => $pendingAgent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['step' => 'action on pending agent'],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson("/api/v1/approvals/{$approval->id}/reject", [
            'justification' => 'No se puede aprobar en un agente no aprovisionado',
        ]);

    $response->assertStatus(400)
        ->assertJsonPath('error', 'RejectionFailed');
});

test('operator role rejects pending execution with mandatory justification and delegates to jg_agente', function () {
    Http::fake([
        'http://test-agent:8000/executions/thread-hitl-reject-200/reject' => Http::response([
            'status' => 'cancelled',
            'thread_id' => 'thread-hitl-reject-200',
            'message' => 'Ejecución cancelada tras rechazo humano.',
        ], 200),
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'thread-hitl-reject-200',
        'agent_id' => $this->activeAgent->agent_id,
        'task_id' => 'task-200',
        'status' => 'PENDING',
        'risk_level' => 'CRITICAL',
        'proposed_plan_json' => [
            'summary' => 'Borrado masivo de particiones de logs',
            'tools_involved' => ['LogPurgerTool'],
        ],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson("/api/v1/approvals/{$approval->id}/reject", [
            'justification' => 'Riesgo inaceptable de pérdida de auditoría según política de seguridad.',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'rejected')
        ->assertJsonPath('data.approval.status', 'REJECTED')
        ->assertJsonPath('data.approval.thread_id', 'thread-hitl-reject-200')
        ->assertJsonPath('data.agent_response.status', 'cancelled');

    // Verify agent received request with signed headers and correct payload
    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://test-agent:8000/executions/thread-hitl-reject-200/reject'
            && $request['thread_id'] === 'thread-hitl-reject-200'
            && $request['approved'] === false
            && $request['rejected'] === true
            && $request['justification'] === 'Riesgo inaceptable de pérdida de auditoría según política de seguridad.'
            && $request->hasHeader('X-Signature')
            && $request->hasHeader('X-Control-Plane');
    });

    // Verify database was updated
    $fresh = $approval->fresh();
    expect($fresh->status)->toBe('REJECTED')
        ->and($fresh->user_id)->toBe($this->operatorUser->id)
        ->and($fresh->justification)->toBe('Riesgo inaceptable de pérdida de auditoría según política de seguridad.')
        ->and($fresh->resolved_at)->not->toBeNull();

    // Verify audit event was recorded
    $audit = AuditEvent::where('event_type', 'hitl.rejected')->latest()->first();
    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($this->operatorUser->id)
        ->and($audit->agent_id)->toBe($this->activeAgent->agent_id)
        ->and($audit->payload_json['thread_id'])->toBe('thread-hitl-reject-200')
        ->and($audit->payload_json['justification'])->toBe('Riesgo inaceptable de pérdida de auditoría según política de seguridad.')
        ->and($audit->payload_json['risk_level'])->toBe('CRITICAL');
});

test('admin role can reject pending execution through role hierarchy', function () {
    Http::fake([
        'http://test-agent:8000/executions/thread-admin-reject/reject' => Http::response([
            'status' => 'cancelled',
            'thread_id' => 'thread-admin-reject',
        ], 200),
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'thread-admin-reject',
        'agent_id' => $this->activeAgent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'purge test'],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
        ->postJson("/api/v1/approvals/{$approval->id}/reject", [
            'justification' => 'Cancelación administrativa de emergencia',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.approval.status', 'REJECTED');

    expect($approval->fresh()->user_id)->toBe($this->adminUser->id);
});

test('handles network failure or agent error and leaves approval in PENDING status', function () {
    Http::fake([
        'http://test-agent:8000/executions/thread-reject-failure/reject' => Http::response([
            'detail' => 'Agent internal failure during rejection',
        ], 500),
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'thread-reject-failure',
        'agent_id' => $this->activeAgent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'reject failure test'],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson("/api/v1/approvals/{$approval->id}/reject", [
            'justification' => 'Intento de rechazo con agente caído',
        ]);

    $response->assertStatus(500)
        ->assertJsonPath('error', 'RejectionFailed');

    // Verify approval remained PENDING so operator can retry
    expect($approval->fresh()->status)->toBe('PENDING')
        ->and($approval->fresh()->resolved_at)->toBeNull();
});

test('root alias /api/approvals/{id}/reject functions identically', function () {
    Http::fake([
        'http://test-agent:8000/executions/thread-alias-reject/reject' => Http::response([
            'status' => 'cancelled',
            'thread_id' => 'thread-alias-reject',
        ], 200),
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'thread-alias-reject',
        'agent_id' => $this->activeAgent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'alias reject test'],
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->operatorToken}")
        ->postJson("/api/approvals/{$approval->id}/reject", [
            'justification' => 'Rechazado mediante alias raíz',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'rejected');

    expect($approval->fresh()->status)->toBe('REJECTED');
});
