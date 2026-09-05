<?php

use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\User;

test('approval request creation with defaults and json casts', function () {
    $agent = Agent::create(['name' => 'agent-hitl-01']);

    $approval = ApprovalRequest::create([
        'thread_id' => 'thread-langgraph-123',
        'agent_id' => $agent->agent_id,
        'proposed_plan_json' => [
            'command' => 'systemctl restart nginx',
            'impact' => 'temporary downtime',
        ],
        'compensation_commands_json' => [
            'rollback' => 'systemctl start nginx',
        ],
    ]);

    expect($approval->id)->not->toBeNull()
        ->and($approval->status)->toBe('PENDING')
        ->and($approval->risk_level)->toBe('HIGH')
        ->and($approval->isPending())->toBeTrue()
        ->and($approval->isResolved())->toBeFalse()
        ->and($approval->proposed_plan_json)->toBeArray()
        ->and($approval->proposed_plan_json['command'])->toBe('systemctl restart nginx')
        ->and($approval->compensation_commands_json['rollback'])->toBe('systemctl start nginx');

    // Test relation with Agent
    expect($approval->agent->agent_id)->toBe($agent->agent_id);
    expect($agent->approvalRequests)->toHaveCount(1);
});

test('approval request approve flow records operator justification and timestamp', function () {
    $agent = Agent::create(['name' => 'agent-hitl-02']);
    $operator = User::create([
        'name' => 'Operator Jane',
        'email' => 'jane@example.com',
        'password' => 'secret123',
        'role' => 'operator',
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'thread-hitl-456',
        'agent_id' => $agent->agent_id,
        'proposed_plan_json' => ['action' => 'migrate_db'],
    ]);

    $result = $approval->approve($operator, 'Plan verificado y validado.');

    expect($result)->toBeTrue()
        ->and($approval->fresh()->status)->toBe('APPROVED')
        ->and($approval->fresh()->isApproved())->toBeTrue()
        ->and($approval->fresh()->isResolved())->toBeTrue()
        ->and($approval->fresh()->user_id)->toBe($operator->id)
        ->and($approval->fresh()->justification)->toBe('Plan verificado y validado.')
        ->and($approval->fresh()->resolved_at)->not->toBeNull();

    // Verify reverse relation on User
    expect($operator->approvalRequests)->toHaveCount(1);
    expect($operator->approvalRequests->first()->id)->toBe($approval->id);

    // Prevent double approval or approving a resolved request
    expect($approval->fresh()->approve($operator, 'Another'))->toBeFalse();
});

test('approval request reject flow records justification', function () {
    $admin = User::create([
        'name' => 'Admin Boss',
        'email' => 'boss@example.com',
        'password' => 'adminpass',
        'role' => 'admin',
    ]);

    $approval = ApprovalRequest::create([
        'thread_id' => 'thread-hitl-789',
        'proposed_plan_json' => ['action' => 'drop_table_users'],
        'risk_level' => 'CRITICAL',
    ]);

    $result = $approval->reject($admin, 'Acción destructiva no autorizada');

    expect($result)->toBeTrue()
        ->and($approval->fresh()->status)->toBe('REJECTED')
        ->and($approval->fresh()->isRejected())->toBeTrue()
        ->and($approval->fresh()->user_id)->toBe($admin->id)
        ->and($approval->fresh()->justification)->toBe('Acción destructiva no autorizada');

    // Prevent rejecting an already resolved request
    expect($approval->fresh()->reject($admin, 'Another'))->toBeFalse();
});

test('approval request query scopes', function () {
    ApprovalRequest::create([
        'thread_id' => 't-1',
        'status' => 'PENDING',
        'risk_level' => 'CRITICAL',
        'proposed_plan_json' => ['a' => 1],
    ]);

    ApprovalRequest::create([
        'thread_id' => 't-2',
        'status' => 'APPROVED',
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['a' => 2],
    ]);

    ApprovalRequest::create([
        'thread_id' => 't-3',
        'status' => 'REJECTED',
        'risk_level' => 'MEDIUM',
        'proposed_plan_json' => ['a' => 3],
    ]);

    expect(ApprovalRequest::pending()->count())->toBe(1)
        ->and(ApprovalRequest::approved()->count())->toBe(1)
        ->and(ApprovalRequest::rejected()->count())->toBe(1)
        ->and(ApprovalRequest::resolved()->count())->toBe(2)
        ->and(ApprovalRequest::byRisk('critical')->count())->toBe(1);
});

test('audit event creation, relations and record helper', function () {
    $user = User::create([
        'name' => 'Auditor',
        'email' => 'audit@example.com',
        'password' => 'secret',
        'role' => 'admin',
    ]);

    $agent = Agent::create(['name' => 'audited-agent']);

    $event = AuditEvent::record(
        eventType: 'AGENT_REGISTRATION',
        actionSummary: 'Agente aprovisionado mediante bootstrap token',
        payload: ['ip' => '10.0.0.15', 'method' => 'POST'],
        user: $user,
        agent: $agent,
        ipAddress: '192.168.1.50',
        userAgent: 'jg_agente_v1.0.0'
    );

    expect($event->id)->not->toBeNull()
        ->and($event->event_type)->toBe('AGENT_REGISTRATION')
        ->and($event->action_summary)->toBe('Agente aprovisionado mediante bootstrap token')
        ->and($event->payload_json)->toBeArray()
        ->and($event->payload_json['ip'])->toBe('10.0.0.15')
        ->and($event->ip_address)->toBe('192.168.1.50')
        ->and($event->user_agent)->toBe('jg_agente_v1.0.0')
        ->and($event->user_id)->toBe($user->id)
        ->and($event->agent_id)->toBe($agent->agent_id)
        ->and($event->created_at)->not->toBeNull();

    // Verify relations
    expect($event->user->id)->toBe($user->id);
    expect($event->agent->agent_id)->toBe($agent->agent_id);
    expect($user->auditEvents)->toHaveCount(1);
    expect($agent->auditEvents)->toHaveCount(1);
});

test('audit event query scopes', function () {
    $agent = Agent::create(['name' => 'agent-scoped']);
    $user = User::create([
        'name' => 'Scoped User',
        'email' => 'scoped@example.com',
        'password' => 'secret',
        'role' => 'operator',
    ]);

    AuditEvent::record(
        eventType: 'HITL_APPROVED',
        actionSummary: 'Plan aprobado',
        user: $user,
        agent: $agent
    );

    AuditEvent::record(
        eventType: 'USER_LOGIN',
        actionSummary: 'Login operador',
        user: $user
    );

    expect(AuditEvent::ofType('HITL_APPROVED')->count())->toBe(1)
        ->and(AuditEvent::ofType('USER_LOGIN')->count())->toBe(1)
        ->and(AuditEvent::forAgent($agent->agent_id)->count())->toBe(1)
        ->and(AuditEvent::forUser($user->id)->count())->toBe(2);
});
