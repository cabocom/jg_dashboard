<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake();
    Config::set('agent.base_url', 'http://test-agent:8000');
    Config::set('agent.internal_secret', 'internal_secret_test');
    Config::set('agent.timeout', 2);
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
        'metadata_json' => ['api_url' => 'http://test-agent:8000', 'secret' => 'test-secret-123'],
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

    $task = \App\Models\AgentTask::create([
        'task_id' => 'task-approve-web-1',
        'thread_id' => 'th-approve-web-1',
        'agent_id' => $this->agent->agent_id,
        'status' => 'WAITING_APPROVAL',
        'payload_json' => ['prompt' => 'Plan aprobado desde consola'],
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

    $task->refresh();
    expect($task->status)->toBe('PROCESSING');

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

    $task = \App\Models\AgentTask::create([
        'task_id' => 'task-reject-web-1',
        'thread_id' => 'th-reject-web-1',
        'agent_id' => $this->agent->agent_id,
        'status' => 'WAITING_APPROVAL',
        'payload_json' => ['prompt' => 'Test plan a rechazar'],
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

    $task->refresh();
    expect($task->status)->toBe('CANCELLED');

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

class StrictVerifyCsrfToken extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}

test('POST /console/approvals/{id}/approve rejects request with HTTP 419 when CSRF token is missing', function () {
    $this->app->bind(
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        StrictVerifyCsrfToken::class
    );

    $approval = ApprovalRequest::create([
        'thread_id' => 'th-csrf-app-1',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'Plan de prueba CSRF'],
    ]);

    $response = $this->actingAs($this->operatorUser)
        ->post("/console/approvals/{$approval->id}/approve", [
            'justification' => 'Intento sin token CSRF',
        ]);

    $response->assertStatus(419);
});

test('POST /console/approvals/{id}/reject rejects request with HTTP 419 when CSRF token is missing', function () {
    $this->app->bind(
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        StrictVerifyCsrfToken::class
    );

    $approval = ApprovalRequest::create([
        'thread_id' => 'th-csrf-rej-1',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'Plan de prueba CSRF rechazo'],
    ]);

    $response = $this->actingAs($this->operatorUser)
        ->post("/console/approvals/{$approval->id}/reject", [
            'justification' => 'Intento de rechazo sin token CSRF',
        ]);

    $response->assertStatus(419);
});

test('POST /console/approvals/{id}/approve accepts request when valid CSRF token is provided via X-CSRF-TOKEN header', function () {
    $this->app->bind(
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        StrictVerifyCsrfToken::class
    );

    Http::fake([
        'http://test-agent:8000/api/v1/commands/resume' => Http::response([
            'success' => true,
            'status' => 'resumed',
        ], 200),
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'th-csrf-valid-1',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'Plan válido con CSRF'],
    ]);

    $token = 'test-valid-csrf-token-string-12345';

    $response = $this->withMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
        ->withSession(['_token' => $token])
        ->actingAs($this->operatorUser)
        ->post("/console/approvals/{$approval->id}/approve", [
            'justification' => 'Aprobado legítimamente con token CSRF',
        ], [
            'X-CSRF-TOKEN' => $token,
            'Accept' => 'application/json',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'approved');

    $approval->refresh();
    expect($approval->status)->toBe('APPROVED');
});

test('POST /console/approvals/{id}/reject accepts request when valid CSRF token is provided via X-CSRF-TOKEN header', function () {
    $this->app->bind(
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        StrictVerifyCsrfToken::class
    );

    Http::fake([
        'http://test-agent:8000/api/v1/commands/cancel' => Http::response([
            'success' => true,
            'status' => 'cancelled',
        ], 200),
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'th-csrf-valid-rej-1',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'Plan a rechazar legítimamente'],
    ]);

    $token = 'test-valid-csrf-token-string-67890';

    $response = $this->withSession(['_token' => $token])
        ->actingAs($this->operatorUser)
        ->post("/console/approvals/{$approval->id}/reject", [
            'justification' => 'Rechazado legítimamente con token CSRF válido',
        ], [
            'X-CSRF-TOKEN' => $token,
            'Accept' => 'application/json',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'rejected');

    $approval->refresh();
    expect($approval->status)->toBe('REJECTED');
});

test('POST /console/executions/trigger rejects request with HTTP 419 when CSRF token is missing', function () {
    $this->app->bind(
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        StrictVerifyCsrfToken::class
    );

    $response = $this->actingAs($this->operatorUser)
        ->post("/console/executions/trigger", [
            'agent_id' => $this->agent->agent_id,
            'prompt' => 'Ejecutar comando no autorizado vía CSRF',
        ]);

    $response->assertStatus(419);
});

test('POST /console/agents rejects request with HTTP 419 when CSRF token is missing', function () {
    $this->app->bind(
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        StrictVerifyCsrfToken::class
    );

    $adminUser = User::create([
        'name' => 'Admin CSRF',
        'email' => 'admin.csrf@example.com',
        'password' => Hash::make('secret123'),
        'role' => 'admin',
    ]);

    $response = $this->actingAs($adminUser)
        ->post("/console/agents", [
            'name' => 'Malicious Agent Provisioned via CSRF',
        ]);

    $response->assertStatus(419);
});

test('GET /console/approvals/pending automatically reconciles orphan WAITING_APPROVAL tasks', function () {
    \App\Models\AgentTask::create([
        'task_id' => 'task-orphan-01',
        'thread_id' => 'th-orphan-01',
        'agent_id' => $this->agent->agent_id,
        'status' => 'WAITING_APPROVAL',
        'payload_json' => [
            'agent_id' => $this->agent->agent_id,
            'prompt' => 'Reiniciar contenedor crítico',
        ],
        'result_json' => [
            'risk_assessment' => ['risk_level' => 'HIGH'],
            'plan' => [
                'goal' => 'Reiniciar contenedor crítico',
                'steps' => [
                    ['tool_name' => 'docker_restart', 'tool_args' => ['container' => 'web_app']],
                ],
            ],
            'cognitive_steps' => [
                ['step_id' => 'cog-1', 'node' => 'risk_assess', 'title' => 'Evaluación de Riesgo'],
            ],
        ],
    ]);

    expect(ApprovalRequest::where('thread_id', 'th-orphan-01')->exists())->toBeFalse();

    $response = $this->get('/console/approvals/pending');

    $response->assertStatus(200);
    expect(ApprovalRequest::where('thread_id', 'th-orphan-01')->exists())->toBeTrue();
    $approval = ApprovalRequest::where('thread_id', 'th-orphan-01')->first();
    expect($approval->status)->toBe('PENDING');
    expect($approval->risk_level)->toBe('HIGH');
    expect($response->json('summary.total_pending'))->toBeGreaterThanOrEqual(1);
});

test('GET /console/executions syncs PROCESSING task into WAITING_APPROVAL and registers ApprovalRequest', function () {
    $task = \App\Models\AgentTask::create([
        'task_id' => 'task-sync-hitl-01',
        'thread_id' => 'th-sync-hitl-01',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PROCESSING',
        'created_at' => now(),
        'started_at' => now(),
        'payload_json' => [
            'agent_id' => $this->agent->agent_id,
            'prompt' => 'Reiniciar servicio borsalino',
        ],
    ]);

    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        return Http::response([
            'status' => 'waiting_approval',
            'data' => [
                'status' => 'waiting_approval',
                'risk_assessment' => ['risk_level' => 'HIGH'],
                'plan' => [
                    'goal' => 'Reiniciar servicio borsalino',
                    'steps' => [['tool_name' => 'docker_restart']],
                ],
                'cognitive_steps' => [
                    ['step_id' => 'cog-risk-1', 'status' => 'waiting_approval'],
                ],
            ],
        ], 200);
    });

    expect(ApprovalRequest::where('thread_id', 'th-sync-hitl-01')->exists())->toBeFalse();

    $response = $this->get('/console/executions');

    $response->assertStatus(200);
    $task->refresh();
    expect($task->status)->toBe('WAITING_APPROVAL');
    expect(ApprovalRequest::where('thread_id', 'th-sync-hitl-01')->exists())->toBeTrue();
    $approval = ApprovalRequest::where('thread_id', 'th-sync-hitl-01')->first();
    expect($approval->status)->toBe('PENDING');
});

test('GET /console/approvals/pending does NOT resurrect already REJECTED approvals and marks orphan task CANCELLED', function () {
    $approval = ApprovalRequest::create([
        'thread_id' => 'th-rejected-persistent',
        'task_id' => 'task-rejected-persistent',
        'agent_id' => $this->agent->agent_id,
        'status' => 'REJECTED',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['prompt' => 'Reiniciar base de datos'],
        'justification' => 'Operación denegada permanentemente',
        'resolved_at' => now(),
    ]);

    $task = \App\Models\AgentTask::create([
        'task_id' => 'task-rejected-persistent',
        'thread_id' => 'th-rejected-persistent',
        'agent_id' => $this->agent->agent_id,
        'status' => 'WAITING_APPROVAL',
        'payload_json' => [
            'agent_id' => $this->agent->agent_id,
            'prompt' => 'Reiniciar base de datos',
        ],
    ]);

    $response = $this->get('/console/approvals/pending');

    $response->assertStatus(200);
    // Debe haber exactamente 1 aprobación y seguir en REJECTED, sin crear una nueva en PENDING
    expect(ApprovalRequest::where('thread_id', 'th-rejected-persistent')->count())->toBe(1);
    expect(ApprovalRequest::where('thread_id', 'th-rejected-persistent')->where('status', 'PENDING')->exists())->toBeFalse();

    // Y la tarea huérfana debe haber sido sincronizada a CANCELLED
    $task->refresh();
    expect($task->status)->toBe('CANCELLED');
});



