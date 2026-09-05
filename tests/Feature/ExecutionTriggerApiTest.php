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
        'name' => 'Admin Test',
        'email' => 'admin@test.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
    ]);

    $this->operatorUser = User::create([
        'name' => 'Operator Test',
        'email' => 'operator@test.com',
        'password' => Hash::make('password123'),
        'role' => 'operator',
    ]);

    $this->viewerUser = User::create([
        'name' => 'Viewer Test',
        'email' => 'viewer@test.com',
        'password' => Hash::make('password123'),
        'role' => 'viewer',
    ]);

    $this->adminToken = $this->jwtService->generateToken($this->adminUser);
    $this->operatorToken = $this->jwtService->generateToken($this->operatorUser);
    $this->viewerToken = $this->jwtService->generateToken($this->viewerUser);

    $this->activeAgent = Agent::create([
        'agent_id' => (string) Str::uuid(),
        'name' => 'Agent-Core-01',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', 'agent_sec_123'),
        'last_heartbeat_at' => now(),
        'metadata_json' => ['api_url' => 'http://test-agent:8000'],
    ]);
});

test('unauthenticated request to POST /api/v1/executions/trigger returns 401 unauthorized', function () {
    $response = $this->postJson('/api/v1/executions/trigger', [
        'prompt' => 'Analizar logs del sistema',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
        ]);
});

test('viewer role receives 403 forbidden on POST /api/v1/executions/trigger', function () {
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->viewerToken)
        ->postJson('/api/v1/executions/trigger', [
            'prompt' => 'Analizar métricas',
        ]);

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'Forbidden',
            'message' => 'Acceso denegado: rol insuficiente',
        ]);
});

test('validation returns 422 when prompt is missing', function () {
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->operatorToken)
        ->postJson('/api/v1/executions/trigger', [
            'agent_id' => $this->activeAgent->agent_id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['prompt', 'user_prompt']);
});

test('returns 404 when requested agent_id does not exist', function () {
    $nonExistentId = (string) Str::uuid();

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->operatorToken)
        ->postJson('/api/v1/executions/trigger', [
            'prompt' => 'Reiniciar nodo worker',
            'agent_id' => $nonExistentId,
        ]);

    $response->assertStatus(404)
        ->assertJson([
            'error' => 'Not Found',
        ]);
});

test('returns 400 when requested agent is OFFLINE or PENDING', function () {
    $offlineAgent = Agent::create([
        'agent_id' => (string) Str::uuid(),
        'name' => 'Agent-Worker-Offline',
        'status' => 'OFFLINE',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->operatorToken)
        ->postJson('/api/v1/executions/trigger', [
            'prompt' => 'Ejecutar diagnóstico',
            'agent_id' => $offlineAgent->agent_id,
        ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Agent Unavailable',
        ]);
});

test('operator role triggers execution and receives 200 OK with execution_id', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec_abc_123',
            'thread_id' => 'thread_xyz_456',
            'status' => 'completed',
            'requires_approval' => false,
            'final_output' => 'Análisis de cluster completado sin incidencias.',
            'plan' => ['Paso 1: Lectura de nodos', 'Paso 2: Verificación'],
        ], 200),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->operatorToken)
        ->postJson('/api/v1/executions/trigger', [
            'prompt' => 'Diagnosticar estado del cluster Kubernetes',
            'agent_id' => $this->activeAgent->agent_id,
            'thread_id' => 'thread_xyz_456',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'execution_id' => 'exec_abc_123',
            'thread_id' => 'thread_xyz_456',
            'status' => 'completed',
            'requires_approval' => false,
            'output' => 'Análisis de cluster completado sin incidencias.',
        ]);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://test-agent:8000/executions/trigger' &&
            $request['prompt'] === 'Diagnosticar estado del cluster Kubernetes' &&
            $request['thread_id'] === 'thread_xyz_456' &&
            $request['user_context']['role'] === 'operator' &&
            $request['user_context']['email'] === 'operator@test.com' &&
            $request->hasHeader('X-Control-Plane', 'jg_dashboard') &&
            $request->hasHeader('X-Signature');
    });

    expect(AuditEvent::where('event_type', 'execution.triggered')->exists())->toBeTrue();
});

test('admin role can trigger execution via role hierarchy', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec_admin_1',
            'thread_id' => 'th_admin_1',
            'status' => 'completed',
            'final_output' => 'Admin task ok',
        ], 200),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
        ->postJson('/api/v1/executions/trigger', [
            'user_prompt' => 'Revisar certificados TLS',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'execution_id' => 'exec_admin_1',
            'status' => 'completed',
        ]);
});

test('async trigger execution returns 202 Accepted', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec_async_99',
            'thread_id' => 'th_async_99',
            'status' => 'in_progress',
            'requires_approval' => false,
        ], 202),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->operatorToken)
        ->postJson('/api/v1/executions/trigger', [
            'prompt' => 'Copia de seguridad en segundo plano',
            'async' => true,
        ]);

    $response->assertStatus(202)
        ->assertJson([
            'execution_id' => 'exec_async_99',
            'status' => 'in_progress',
        ]);
});

test('creates ApprovalRequest in database when execution requires HITL approval', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec_hitl_55',
            'thread_id' => 'thread_hitl_55',
            'status' => 'waiting_approval',
            'requires_approval' => true,
            'risk_level' => 'CRITICAL',
            'plan' => [
                'step' => 'Eliminar base de datos corrupta',
                'action' => 'drop_database',
            ],
            'compensation_commands' => ['restore_backup_point'],
        ], 200),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->operatorToken)
        ->postJson('/api/v1/executions/trigger', [
            'prompt' => 'Purgar registros dañados',
            'agent_id' => $this->activeAgent->agent_id,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'waiting_approval',
            'requires_approval' => true,
        ]);

    $approval = ApprovalRequest::where('thread_id', 'thread_hitl_55')->first();
    expect($approval)->not->toBeNull();
    expect($approval->status)->toBe('PENDING');
    expect($approval->risk_level)->toBe('CRITICAL');
    expect($approval->proposed_plan_json)->toHaveKey('action', 'drop_database');
});

test('handles network failure or unreachable agent with 502/503 and structured error', function () {
    Http::fake([
        'http://test-agent:8000/*' => function () {
            throw new ConnectionException('Connection refused by jg_agente');
        },
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->operatorToken)
        ->postJson('/api/v1/executions/trigger', [
            'prompt' => 'Tarea hacia agente caído',
            'agent_id' => $this->activeAgent->agent_id,
        ]);

    $response->assertStatus(503)
        ->assertJson([
            'error' => 'Agent Gateway Error',
        ])
        ->assertJsonStructure(['error', 'message', 'execution_id', 'thread_id']);
});

test('root alias /api/executions/trigger functions identically', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec_alias_01',
            'thread_id' => 'th_alias_01',
            'status' => 'completed',
            'final_output' => 'Alias ok',
        ], 200),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->operatorToken)
        ->postJson('/api/executions/trigger', [
            'prompt' => 'Comprobar alias de rutas',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'execution_id' => 'exec_alias_01',
            'output' => 'Alias ok',
        ]);
});
