<?php

use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('agent.base_url', 'http://test-agent:8000');
    Config::set('agent.internal_secret', 'internal_secret_test');
    Config::set('agent.timeout', 5);
    Config::set('agent.retry_times', 0);

    $this->operatorUser = User::create([
        'name' => 'Console Operator',
        'email' => 'operator.web@example.com',
        'password' => Hash::make('secret123'),
        'role' => 'operator',
    ]);

    $this->agent = Agent::create([
        'agent_id' => 'agent-hitl-web-01',
        'name' => 'Agent SRE Kubernetes',
        'status' => 'ACTIVE',
        'metadata_json' => ['api_url' => 'http://test-agent:8000'],
    ]);

    $this->actingAs($this->operatorUser);
});

test('GET /console renders the interactive HITL console toolbar, filter pills and inspector modal', function () {
    $response = $this->get('/console');

    $response->assertStatus(200)
        ->assertSee('id="hitl-search"', false)
        ->assertSee('id="hitl-filter-agent"', false)
        ->assertSee('data-risk="ALL"', false)
        ->assertSee('data-risk="CRITICAL"', false)
        ->assertSee('data-risk="HIGH"', false)
        ->assertSee('id="modal-inspect-plan"', false)
        ->assertSee('id="inspect-plan-content"', false)
        ->assertSee('id="btn-copy-plan-json"', false)
        ->assertSee('id="modal-approve"', false)
        ->assertSee('id="modal-reject"', false);
});

test('GET /console/approvals/pending returns pending approvals with steps breakdown and summary', function () {
    ApprovalRequest::create([
        'thread_id' => 'th-console-1',
        'agent_id' => $this->agent->agent_id,
        'task_id' => 'task-c1',
        'status' => 'PENDING',
        'risk_level' => 'CRITICAL',
        'proposed_plan_json' => [
            'summary' => 'Reiniciar nodo master de Kubernetes',
            'tools_involved' => ['kubectl', 'systemctl'],
            'steps' => [
                ['action' => 'Drenar pods del nodo', 'tool' => 'kubectl', 'command' => 'kubectl drain node-01 --ignore-daemonsets'],
                ['action' => 'Reiniciar servicio kubelet', 'tool' => 'systemctl', 'command' => 'systemctl restart kubelet'],
            ],
        ],
        'compensation_commands_json' => [
            'kubectl uncordon node-01',
        ],
    ]);

    $response = $this->getJson('/console/approvals/pending');

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
                    'elapsed_time',
                ],
            ],
            'summary' => [
                'total_pending',
                'critical_count',
                'high_count',
                'other_count',
            ],
            'meta' => [
                'total',
            ],
        ])
        ->assertJsonPath('summary.total_pending', 1)
        ->assertJsonPath('summary.critical_count', 1);

    expect($response->json('data.0.thread_id'))->toBe('th-console-1');
    expect($response->json('data.0.proposed_plan.steps.0.tool'))->toBe('kubectl');
});

test('POST /console/approvals/{id}/approve approves request and delegates command to jg_agente', function () {
    Http::fake([
        'http://test-agent:8000/executions/th-approve-web-1/approve' => Http::response([
            'status' => 'resumed',
            'thread_id' => 'th-approve-web-1',
            'checkpoint_id' => 'chk-approved-01',
            'message' => 'Grafo cognitivo reanudado.',
        ], 200),
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'th-approve-web-1',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'CRITICAL',
        'proposed_plan_json' => ['action' => 'Plan aprobado desde consola'],
    ]);

    $response = $this->actingAs($this->operatorUser)
        ->postJson("/console/approvals/{$approval->id}/approve", [
            'justification' => 'Aprobado desde la consola web por el operador de guardia.',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'approved');

    $approval->refresh();
    expect($approval->status)->toBe('APPROVED');
    expect($approval->user_id)->toBe($this->operatorUser->id);

    $audit = AuditEvent::where('event_type', 'hitl.approved')->latest()->first();
    expect($audit)->not->toBeNull();
    expect($audit->agent_id)->toBe($this->agent->agent_id);
});

test('POST /console/approvals/{id}/reject rejects request with mandatory justification and cancels on jg_agente', function () {
    Http::fake([
        'http://test-agent:8000/executions/th-reject-web-1/reject' => Http::response([
            'status' => 'cancelled',
            'thread_id' => 'th-reject-web-1',
            'checkpoint_id' => 'chk-rejected-01',
            'message' => 'Ejecución cancelada por rechazo humano.',
        ], 200),
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'th-reject-web-1',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'Plan a rechazar'],
    ]);

    $response = $this->actingAs($this->operatorUser)
        ->postJson("/console/approvals/{$approval->id}/reject", [
            'justification' => 'Rechazado por riesgo de caída del clúster.',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'rejected');

    $approval->refresh();
    expect($approval->status)->toBe('REJECTED');
    expect($approval->justification)->toBe('Rechazado por riesgo de caída del clúster.');

    $audit = AuditEvent::where('event_type', 'hitl.rejected')->latest()->first();
    expect($audit)->not->toBeNull();
});

test('POST /console/approvals/{id}/reject requires minimum 5 characters justification', function () {
    $approval = ApprovalRequest::create([
        'thread_id' => 'th-val-1',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'Test'],
    ]);

    $response = $this->postJson("/console/approvals/{$approval->id}/reject", [
        'justification' => 'no',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['justification']);
});
