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

test('GET /console renders the real-time cognitive jumps viewer in launcher and execution trace modal', function () {
    $response = $this->actingAs($this->operatorUser)->get('/console');

    $response->assertStatus(200)
        ->assertSee('Saltos Cognitivos y Transiciones de Nodos')
        ->assertSee('id="execution-steps-timeline"', false)
        ->assertSee('id="execution-output-box"', false)
        ->assertSee('id="modal-execution-trace"', false)
        ->assertSee('id="modal-trace-steps-timeline"', false)
        ->assertSee('id="modal-trace-output"', false);
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

test('GET /console renders separated launcher and executions history views with agent filters', function () {
    $response = $this->actingAs($this->operatorUser)->get('/console');

    $response->assertStatus(200)
        ->assertSee('id="nav-launcher"', false)
        ->assertSee('id="nav-executions"', false)
        ->assertSee('id="view-launcher"', false)
        ->assertSee('id="view-executions"', false)
        ->assertSee('id="executions-filter-agent-id"', false)
        ->assertSee('id="executions-filter-status"', false)
        ->assertSee('id="executions-filter-date-from"', false)
        ->assertSee('id="executions-filter-date-to"', false)
        ->assertSee('id="executions-search"', false)
        ->assertSee('data-sort="created_at"', false)
        ->assertSee('data-sort="prompt"', false)
        ->assertSee('data-sort="user"', false)
        ->assertSee('id="modal-agent-capabilities"', false)
        ->assertSee('id="agent-caps-json-content"', false)
        ->assertSee('id="table-executions-history"', false)
        ->assertSee('id="btn-trigger-execution"', false)
        ->assertSee('Historial de Ejecuciones Recientes')
        ->assertSee('Pasarela de Disparo de Ejecuciones')
        ->assertSee('Lanzar Ejecución')
        ->assertSee('Agente:')
        ->assertSee('Usuario')
        ->assertSee('Todos los derechos reservados por')
        ->assertSee('https://javiergalvez.com')
        ->assertSee('Javier Gálvez');
});

test('GET /console/executions returns executions list with agent_type, user and supports filtering by agent_type', function () {
    $convAgent = Agent::create([
        'agent_id' => 'agent-conv-01',
        'name' => 'Agente de Asistencia Virtual',
        'status' => 'ACTIVE',
        'metadata_json' => ['plugin' => 'conversational'],
    ]);

    \App\Models\AgentTask::create([
        'task_id' => 'task-devops-01',
        'thread_id' => 'th-devops-01',
        'agent_id' => $this->agent->agent_id,
        'status' => 'COMPLETED',
        'created_at' => now()->subMinutes(10),
        'payload_json' => [
            'agent_id' => $this->agent->agent_id,
            'prompt' => 'Desplegar release v2.4.0 en Kubernetes',
            'username' => 'devops_operator',
        ],
        'result_json' => ['output' => 'DEPLOY_OK'],
    ]);

    \App\Models\AgentTask::create([
        'task_id' => 'task-conv-01',
        'thread_id' => 'th-conv-01',
        'agent_id' => $convAgent->agent_id,
        'status' => 'PROCESSING',
        'created_at' => now()->subMinutes(5),
        'payload_json' => [
            'agent_id' => $convAgent->agent_id,
            'prompt' => 'Hola, necesito ayuda para configurar mi cuenta',
            'user_context' => [
                'name' => 'Support User',
                'email' => 'support@example.com',
            ],
        ],
        'result_json' => [],
    ]);

    // 1. Fetch all executions
    $responseAll = $this->actingAs($this->operatorUser)->getJson('/console/executions');
    $responseAll->assertStatus(200)
        ->assertJsonStructure([
            'executions' => [
                '*' => [
                    'task_id',
                    'thread_id',
                    'agent_id',
                    'agent_name',
                    'agent_type',
                    'user',
                    'status',
                    'prompt',
                    'prompt_truncated',
                    'created_at_formatted',
                ],
            ],
        ]);

    $items = collect($responseAll->json('executions'));
    $devopsItem = $items->firstWhere('task_id', 'task-devops-01');
    $convItem = $items->firstWhere('task_id', 'task-conv-01');

    expect($devopsItem)->not->toBeNull()
        ->and($devopsItem['agent_type'])->toBe('DevOps')
        ->and($devopsItem['user'])->toBe('devops_operator')
        ->and($convItem)->not->toBeNull()
        ->and($convItem['agent_type'])->toBe('Conversacional')
        ->and($convItem['user'])->toBe('Support User');

    // 2. Filter by DevOps
    $responseDevOps = $this->actingAs($this->operatorUser)->getJson('/console/executions?agent_type=DevOps');
    $responseDevOps->assertStatus(200);
    $devopsOnly = collect($responseDevOps->json('executions'));
    expect($devopsOnly->pluck('task_id'))->toContain('task-devops-01')
        ->and($devopsOnly->pluck('task_id'))->not->toContain('task-conv-01');

    // 3. Filter by Conversacional
    $responseConv = $this->actingAs($this->operatorUser)->getJson('/console/executions?agent_type=Conversacional');
    $responseConv->assertStatus(200);
    $convOnly = collect($responseConv->json('executions'));
    expect($convOnly->pluck('task_id'))->toContain('task-conv-01')
        ->and($convOnly->pluck('task_id'))->not->toContain('task-devops-01');

    // 4. Filter by specific agent_id
    $responseAgent = $this->actingAs($this->operatorUser)->getJson("/console/executions?agent_id={$this->agent->agent_id}");
    $responseAgent->assertStatus(200);
    $agentOnly = collect($responseAgent->json('executions'));
    expect($agentOnly->pluck('task_id'))->toContain('task-devops-01')
        ->and($agentOnly->pluck('task_id'))->not->toContain('task-conv-01');
});

test('GET /console/agents returns executions_count for each agent', function () {
    \App\Models\AgentTask::create([
        'task_id' => 'task-count-01',
        'thread_id' => 'th-count-01',
        'agent_id' => $this->agent->agent_id,
        'status' => 'COMPLETED',
        'created_at' => now(),
        'payload_json' => ['agent_id' => $this->agent->agent_id, 'prompt' => 'test count'],
    ]);

    $response = $this->actingAs($this->operatorUser)->getJson('/console/agents');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'agent_id',
                    'name',
                    'status',
                    'executions_count',
                ],
            ],
        ]);

    $agentData = collect($response->json('data'))->firstWhere('agent_id', $this->agent->agent_id);
    expect($agentData)->not->toBeNull()
        ->and($agentData['executions_count'])->toBeGreaterThanOrEqual(1);
});

test('GET /console/executions supports date range filtering', function () {
    \App\Models\AgentTask::create([
        'task_id' => 'task-old-01',
        'thread_id' => 'th-old-01',
        'agent_id' => $this->agent->agent_id,
        'status' => 'COMPLETED',
        'created_at' => now()->subDays(5),
        'payload_json' => ['agent_id' => $this->agent->agent_id, 'prompt' => 'old task'],
    ]);

    \App\Models\AgentTask::create([
        'task_id' => 'task-new-01',
        'thread_id' => 'th-new-01',
        'agent_id' => $this->agent->agent_id,
        'status' => 'COMPLETED',
        'created_at' => now(),
        'payload_json' => ['agent_id' => $this->agent->agent_id, 'prompt' => 'new task'],
    ]);

    $dateFrom = now()->subDays(1)->toIso8601String();
    $response = $this->actingAs($this->operatorUser)->getJson("/console/executions?date_from={$dateFrom}");
    $response->assertStatus(200);
    $items = collect($response->json('executions'));

    expect($items->pluck('task_id'))->toContain('task-new-01')
        ->and($items->pluck('task_id'))->not->toContain('task-old-01');
});

test('AgentTaskResource navigation url points directly to /console?tab=executions', function () {
    expect(\App\Filament\Resources\AgentTaskResource::getNavigationUrl())->toContain('/console?tab=executions');
});



