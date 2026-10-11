<?php

use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;

test('hitl:reconcile restores stale PROCESSING approvals back to PENDING and logs audit event', function () {
    $agent = Agent::create([
        'name' => 'hitl-worker-agent',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', 'secret'),
    ]);

    // Solicitud 1: Colgada en PROCESSING desde hace 300 segundos
    $stale = ApprovalRequest::create([
        'thread_id' => 'thread-stale-01',
        'agent_id' => $agent->agent_id,
        'status' => 'PROCESSING',
        'processing_started_at' => now()->subSeconds(300),
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'deploy'],
    ]);

    // Solicitud 2: Procesamiento reciente (hace 10 segundos), no debe tocarse
    $fresh = ApprovalRequest::create([
        'thread_id' => 'thread-fresh-01',
        'agent_id' => $agent->agent_id,
        'status' => 'PROCESSING',
        'processing_started_at' => now()->subSeconds(10),
        'risk_level' => 'HIGH',
        'proposed_plan_json' => ['action' => 'restart'],
    ]);

    $this->artisan('hitl:reconcile', ['--ttl' => 120])
        ->assertExitCode(0)
        ->expectsOutputToContain('Se reconciliaron 1 solicitud(es) colgada(s). Restauradas a PENDING:')
        ->expectsOutputToContain("ID: {$stale->id}");

    expect($stale->fresh()->status)->toBe('PENDING')
        ->and($stale->fresh()->processing_started_at)->toBeNull()
        ->and($fresh->fresh()->status)->toBe('PROCESSING')
        ->and($fresh->fresh()->processing_started_at)->not->toBeNull();

    // Validar trazabilidad en log de auditoría
    $audit = AuditEvent::where('event_type', 'hitl.reconciled_stale_processing')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->payload_json['approval_id'])->toBe($stale->id)
        ->and($audit->payload_json['thread_id'])->toBe('thread-stale-01');
});

test('hitl:reconcile outputs clean message when no stale processing approvals exist', function () {
    $this->artisan('hitl:reconcile', ['--ttl' => 60])
        ->assertExitCode(0)
        ->expectsOutputToContain('No se detectaron solicitudes colgadas en PROCESSING.');
});
