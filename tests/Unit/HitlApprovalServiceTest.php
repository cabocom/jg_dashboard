<?php

use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Hitl\HitlApprovalService;

test('HitlApprovalService registers pending approval with plan, tools and risk level', function () {
    $service = new HitlApprovalService();

    $agent = Agent::create([
        'agent_id' => 'agent-hitl-test-01',
        'name' => 'Agent HITL Worker',
        'status' => 'ACTIVE',
    ]);

    $operator = User::create([
        'name' => 'Operator Alex',
        'email' => 'alex@example.com',
        'password' => 'secret123',
        'role' => 'operator',
    ]);

    $plan = [
        'step' => 'database_migration',
        'impact' => 'table lock expected',
    ];
    $tools = ['DockerEngine', 'PostgresRunner'];
    $compensation = [
        'rollback' => 'docker exec postgres pg_restore',
    ];

    $approval = $service->registerPendingApproval(
        data: [
            'thread_id' => 'th-hitl-100',
            'agent_id' => $agent->agent_id,
            'task_id' => 'task-exec-55',
            'risk_level' => 'CRITICAL',
            'proposed_plan' => $plan,
            'tools' => $tools,
            'compensation_commands' => $compensation,
        ],
        initiator: $operator,
        ipAddress: '10.0.0.42',
        userAgent: 'HitlClient/1.0'
    );

    expect($approval)->toBeInstanceOf(ApprovalRequest::class)
        ->and($approval->thread_id)->toBe('th-hitl-100')
        ->and($approval->agent_id)->toBe($agent->agent_id)
        ->and($approval->task_id)->toBe('task-exec-55')
        ->and($approval->status)->toBe('PENDING')
        ->and($approval->risk_level)->toBe('CRITICAL')
        ->and($approval->proposed_plan_json['step'])->toBe('database_migration')
        ->and($approval->proposed_plan_json['tools_involved'])->toBe($tools)
        ->and($approval->compensation_commands_json['rollback'])->toBe('docker exec postgres pg_restore');

    // Verify audit event
    $audit = AuditEvent::where('event_type', 'hitl.requested')->latest()->first();
    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($operator->id)
        ->and($audit->agent_id)->toBe($agent->agent_id)
        ->and($audit->payload_json['thread_id'])->toBe('th-hitl-100')
        ->and($audit->payload_json['risk_level'])->toBe('CRITICAL')
        ->and($audit->payload_json['tools_count'])->toBe(2)
        ->and($audit->payload_json['has_compensation'])->toBeTrue()
        ->and($audit->ip_address)->toBe('10.0.0.42');
});

test('HitlApprovalService throws InvalidArgumentException when thread_id is missing', function () {
    $service = new HitlApprovalService();

    expect(fn () => $service->registerPendingApproval([]))
        ->toThrow(\InvalidArgumentException::class, "El campo 'thread_id' es obligatorio");
});

test('HitlApprovalService idempotently updates existing pending approval for same thread_id', function () {
    $service = new HitlApprovalService();

    $agent = Agent::create([
        'agent_id' => 'agent-hitl-test-02',
        'name' => 'Agent HITL Update',
        'status' => 'ACTIVE',
    ]);

    $firstApproval = $service->registerPendingApproval([
        'thread_id' => 'th-hitl-idempotent-1',
        'agent_id' => $agent->agent_id,
        'risk_level' => 'HIGH',
        'proposed_plan' => ['step' => 'initial_plan'],
    ]);

    expect(ApprovalRequest::where('thread_id', 'th-hitl-idempotent-1')->count())->toBe(1);

    // Update with new checkpoint/plan
    $updatedApproval = $service->registerPendingApproval([
        'thread_id' => 'th-hitl-idempotent-1',
        'agent_id' => $agent->agent_id,
        'risk_level' => 'CRITICAL',
        'proposed_plan' => ['step' => 'refined_plan_step_2'],
        'tools' => ['DangerousDeployTool'],
    ]);

    expect($updatedApproval->id)->toBe($firstApproval->id)
        ->and(ApprovalRequest::where('thread_id', 'th-hitl-idempotent-1')->count())->toBe(1)
        ->and($updatedApproval->risk_level)->toBe('CRITICAL')
        ->and($updatedApproval->proposed_plan_json['step'])->toBe('refined_plan_step_2')
        ->and($updatedApproval->proposed_plan_json['tools_involved'])->toBe(['DangerousDeployTool']);
});

test('HitlApprovalService getPendingApprovals returns paginated results and filters by agent_id', function () {
    $service = new HitlApprovalService();

    $agent1 = Agent::create(['agent_id' => 'agent-p-1', 'name' => 'Agent 1']);
    $agent2 = Agent::create(['agent_id' => 'agent-p-2', 'name' => 'Agent 2']);

    $service->registerPendingApproval(['thread_id' => 'th-p-1', 'agent_id' => $agent1->agent_id]);
    $service->registerPendingApproval(['thread_id' => 'th-p-2', 'agent_id' => $agent1->agent_id]);
    $service->registerPendingApproval(['thread_id' => 'th-p-3', 'agent_id' => $agent2->agent_id]);

    $allPending = $service->getPendingApprovals(perPage: 10);
    expect($allPending->total())->toBe(3);

    $agent1Pending = $service->getPendingApprovals(agentId: $agent1->agent_id, perPage: 10);
    expect($agent1Pending->total())->toBe(2);

    $agent2Pending = $service->getPendingApprovals(agentId: $agent2->agent_id, perPage: 10);
    expect($agent2Pending->total())->toBe(1);
});

test('HitlApprovalService findApproval and findPendingByThread return approval requests with relations', function () {
    $service = new HitlApprovalService();

    $agent = Agent::create(['agent_id' => 'agent-find-1', 'name' => 'Agent Find']);

    $created = $service->registerPendingApproval([
        'thread_id' => 'th-find-test-999',
        'agent_id' => $agent->agent_id,
        'risk_level' => 'HIGH',
    ]);

    $foundById = $service->findApproval($created->id);
    expect($foundById)->not->toBeNull()
        ->and($foundById->thread_id)->toBe('th-find-test-999')
        ->and($foundById->relationLoaded('agent'))->toBeTrue()
        ->and($foundById->agent->name)->toBe('Agent Find');

    $foundByThread = $service->findPendingByThread('th-find-test-999');
    expect($foundByThread)->not->toBeNull()
        ->and($foundByThread->id)->toBe($created->id);

    $notFound = $service->findPendingByThread('non-existent-thread');
    expect($notFound)->toBeNull();
});
