<?php

use App\Models\Agent;
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

    $this->operatorUser = User::create([
        'name' => 'Console Operator',
        'email' => 'operator.execution@example.com',
        'password' => Hash::make('secret123'),
        'role' => 'operator',
    ]);

    $this->agent = Agent::create([
        'agent_id' => 'agent-exec-web-01',
        'name' => 'Agent DevOps Pipeline',
        'status' => 'ACTIVE',
        'metadata_json' => ['api_url' => 'http://test-agent:8000'],
    ]);
});

test('GET /console renders the execution viewer toolbar, recent threads bar and trace container', function () {
    $response = $this->actingAs($this->operatorUser)->get('/console');

    $response->assertStatus(200)
        ->assertSee('Visor de Traza de Pasos')
        ->assertSee('id="execution-query-agent"', false)
        ->assertSee('id="execution-query-thread"', false)
        ->assertSee('id="btn-load-execution"', false)
        ->assertSee('id="recent-threads-list"', false)
        ->assertSee('id="execution-steps-timeline"', false)
        ->assertSee('id="execution-output-box"', false);
});

test('POST /console/executions/trigger triggers execution and returns execution payload', function () {
    $threadId = (string) Str::uuid();

    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'status' => 'success',
            'thread_id' => $threadId,
            'output' => [
                'message' => 'Ejecución iniciada correctamente.',
            ],
            'trace' => [
                'steps' => [
                    [
                        'step' => 1,
                        'name' => 'discovery',
                        'tool' => 'InfraDiscoveryTool',
                        'status' => 'success',
                        'duration_ms' => 45,
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->operatorUser)->postJson('/console/executions/trigger', [
        'agent_id' => $this->agent->agent_id,
        'prompt' => 'Verificar estado del cluster y desplegar servicio',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('thread_id', $threadId)
        ->assertJsonPath('agent_id', $this->agent->agent_id);
});

test('GET /console/executions/{agent_id}/{thread_id} returns unified execution details', function () {
    $threadId = (string) Str::uuid();

    Http::fake([
        'http://test-agent:8000/executions/' . $threadId => Http::response([
            'status' => 'completed',
            'thread_id' => $threadId,
            'prompt' => 'Desplegar servicio web en puerto 8080',
            'steps' => [
                [
                    'step' => 1,
                    'tool' => 'DockerDeployTool',
                    'status' => 'success',
                    'latency_ms' => 120,
                    'summary' => 'Contenedor levantado en puerto 8080.',
                ],
            ],
            'output' => [
                'result' => 'OK',
                'url' => 'http://localhost:8080',
            ],
            'latency_ms' => 350,
        ], 200),
    ]);

    $response = $this->actingAs($this->operatorUser)->getJson(
        "/console/executions/{$this->agent->agent_id}/{$threadId}"
    );

    $response->assertStatus(200)
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('thread_id', $threadId)
        ->assertJsonPath('agent_id', $this->agent->agent_id)
        ->assertJsonPath('steps.0.tool', 'DockerDeployTool')
        ->assertJsonPath('output.result', 'OK');
});

test('GET /console/executions/{agent_id}/{thread_id} returns 404 for unknown agent', function () {
    $nonExistentId = (string) Str::uuid();

    $response = $this->actingAs($this->operatorUser)->getJson(
        "/console/executions/{$nonExistentId}/test-thread"
    );

    $response->assertStatus(404);
});

test('GET /console renders the dedicated agent capabilities modal and execution history table', function () {
    $response = $this->actingAs($this->operatorUser)->get('/console');

    $response->assertStatus(200)
        ->assertSee('id="modal-agent-capabilities"', false)
        ->assertSee('id="agent-caps-json-content"', false)
        ->assertSee('id="table-executions-history"', false)
        ->assertSee('id="btn-trigger-execution"', false)
        ->assertSee('Historial de Ejecuciones Recientes');
});

test('GET /console/executions returns executions list with truncated prompt and formatted date', function () {
    \App\Models\AgentTask::create([
        'task_id' => 'task-test-exec-100',
        'thread_id' => 'th-uuid-100',
        'agent_id' => $this->agent->agent_id,
        'status' => 'COMPLETED',
        'created_at' => now(),
        'payload_json' => [
            'agent_id' => $this->agent->agent_id,
            'prompt' => 'Este es un prompt de prueba muy largo que debería ser truncado adecuadamente para facilitar la identificación visual en la tabla de la consola de control',
        ],
        'result_json' => ['output' => 'OK'],
    ]);

    $response = $this->actingAs($this->operatorUser)->getJson('/console/executions');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'executions' => [
                '*' => [
                    'task_id',
                    'thread_id',
                    'agent_id',
                    'agent_name',
                    'status',
                    'prompt',
                    'prompt_truncated',
                    'created_at_formatted',
                ],
            ],
        ]);

    $data = $response->json('executions.0');
    expect($data['thread_id'])->toBe('th-uuid-100')
        ->and($data['agent_id'])->toBe($this->agent->agent_id)
        ->and($data['agent_name'])->toBe($this->agent->name)
        ->and($data['status'])->toBe('COMPLETED')
        ->and(mb_strlen($data['prompt_truncated']))->toBeLessThanOrEqual(83)
        ->and($data['created_at_formatted'])->not->toBe('-');
});

test('AgentTaskResource navigation url points directly to /console?tab=executions', function () {
    expect(\App\Filament\Resources\AgentTaskResource::getNavigationUrl())->toContain('/console?tab=executions');
});


