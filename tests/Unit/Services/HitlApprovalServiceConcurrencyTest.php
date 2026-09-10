<?php

use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\Agents\AgentHttpClient;
use App\Services\Hitl\HitlApprovalService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = new HitlApprovalService();

    $this->agent = Agent::create([
        'agent_id' => 'agent-concurrency-01',
        'name' => 'Agent Concurrency Worker',
        'status' => 'ACTIVE',
        'metadata_json' => ['api_url' => 'http://test-agent:8000'],
    ]);

    $this->operator = User::create([
        'name' => 'Operator Concurrency',
        'email' => 'operator.concurrency@example.com',
        'password' => 'secret123',
        'role' => 'operator',
    ]);
});

test('HitlApprovalService rejects approval when request is already in PROCESSING status (409 Conflict)', function () {
    $approval = ApprovalRequest::create([
        'thread_id' => 'th-concurrent-processing-approve',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PROCESSING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['step' => 'step-in-flight'],
    ]);

    $result = $this->service->approveApproval(
        approvalId: $approval->id,
        resolver: $this->operator,
        justification: 'Intentando aprobar mientras ya procesa'
    );

    expect($result['success'])->toBeFalse()
        ->and($result['status_code'])->toBe(409)
        ->and($result['error'])->toContain('ya se encuentra en procesamiento');

    // Sigue en PROCESSING sin corromperse
    expect($approval->fresh()->status)->toBe('PROCESSING');
});

test('HitlApprovalService rejects rejection when request is already in PROCESSING status (409 Conflict)', function () {
    $approval = ApprovalRequest::create([
        'thread_id' => 'th-concurrent-processing-reject',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PROCESSING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['step' => 'step-in-flight'],
    ]);

    $result = $this->service->rejectApproval(
        approvalId: $approval->id,
        resolver: $this->operator,
        justification: 'Intentando rechazar mientras ya procesa'
    );

    expect($result['success'])->toBeFalse()
        ->and($result['status_code'])->toBe(409)
        ->and($result['error'])->toContain('ya se encuentra en procesamiento');

    expect($approval->fresh()->status)->toBe('PROCESSING');
});

test('HitlApprovalService reconciles status back to PENDING when agent throws network exception on approve', function () {
    Http::fake([
        'http://test-agent:8000/executions/th-net-err-approve/approve' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection reset by peer');
        },
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'th-net-err-approve',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'CRITICAL',
        'proposed_plan_json' => ['step' => 'failing_action'],
    ]);

    $result = $this->service->approveApproval(
        approvalId: $approval->id,
        resolver: $this->operator,
        justification: 'Aprobación con fallo de red'
    );

    expect($result['success'])->toBeFalse()
        ->and($result['status_code'])->toBe(503)
        ->and($result['error'])->toContain('fallo de red');

    // Reconciliación exitosa: vuelve a PENDING para reintento seguro
    expect($approval->fresh()->status)->toBe('PENDING')
        ->and($approval->fresh()->resolved_at)->toBeNull();
});

test('HitlApprovalService reconciles status back to PENDING when agent throws network exception on reject', function () {
    Http::fake([
        'http://test-agent:8000/executions/th-net-err-reject/reject' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Timeout contacting agent');
        },
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'th-net-err-reject',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['step' => 'failing_action'],
    ]);

    $result = $this->service->rejectApproval(
        approvalId: $approval->id,
        resolver: $this->operator,
        justification: 'Rechazo con fallo de conexión de red'
    );

    expect($result['success'])->toBeFalse()
        ->and($result['status_code'])->toBe(504)
        ->and($result['error'])->toContain('Timeout');

    // Reconciliación exitosa: vuelve a PENDING
    expect($approval->fresh()->status)->toBe('PENDING')
        ->and($approval->fresh()->resolved_at)->toBeNull();
});

test('HitlApprovalService reconciles status back to PENDING when agent returns 500 error response on approve', function () {
    Http::fake([
        'http://test-agent:8000/executions/th-500-approve/approve' => Http::response([
            'detail' => 'Graph resume failed in agent worker',
        ], 500),
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'th-500-approve',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['step' => 'error_step'],
    ]);

    $result = $this->service->approveApproval(
        approvalId: $approval->id,
        resolver: $this->operator,
        justification: 'Aprobando solicitud'
    );

    expect($result['success'])->toBeFalse()
        ->and($result['status_code'])->toBe(500);

    // Reconciliado a PENDING
    expect($approval->fresh()->status)->toBe('PENDING');
});

test('HitlApprovalService transitions cleanly from PENDING to PROCESSING to APPROVED when agent responds 200', function () {
    Http::fake([
        'http://test-agent:8000/executions/th-success-approve/approve' => Http::response([
            'status' => 'resumed',
            'thread_id' => 'th-success-approve',
        ], 200),
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'th-success-approve',
        'agent_id' => $this->agent->agent_id,
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['step' => 'valid_step'],
    ]);

    $result = $this->service->approveApproval(
        approvalId: $approval->id,
        resolver: $this->operator,
        justification: 'Aprobado correctamente tras revisión'
    );

    expect($result['success'])->toBeTrue()
        ->and($result['status_code'])->toBe(200)
        ->and($approval->fresh()->status)->toBe('APPROVED')
        ->and($approval->fresh()->user_id)->toBe($this->operator->id)
        ->and($approval->fresh()->resolved_at)->not->toBeNull();
});
