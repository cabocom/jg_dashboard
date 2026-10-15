<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

use App\Models\Agent;
use App\Models\AuditEvent;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    Config::set('agent.base_url', 'http://test-agent:8000');
    Config::set('agent.internal_secret', 'internal_secret_test');
    Config::set('agent.timeout', 5);
    Config::set('agent.retry_times', 0);
    Config::set('adapters.webhook_secret', null);
    Config::set('adapters.require_auth', false);

    $this->activeAgent = Agent::create([
        'agent_id' => (string) Str::uuid(),
        'name' => 'Agent-Core-Webhook',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', 'agent_sec_123'),
        'last_heartbeat_at' => now(),
        'metadata_json' => ['api_url' => 'http://test-agent:8000'],
    ]);
});

test('generic external webhook request is normalized to AgentInput, executed and returned in client format', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec-gen-100',
            'thread_id' => 'thread-gen-200',
            'status' => 'COMPLETED',
            'output' => 'Análisis de servidor completado con éxito.',
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/adapters/webhook', [
        'prompt' => 'Revisar estado de memoria RAM',
        'parameters' => ['server' => 'web-01'],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'status' => 'COMPLETED',
            'output' => 'Análisis de servidor completado con éxito.',
        ]);

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        return $request->url() === 'http://test-agent:8000/executions/trigger'
            && $body['prompt'] === 'Revisar estado de memoria RAM'
            && isset($body['execution_id'])
            && isset($body['thread_id'])
            && $body['user_context']['role'] === 'external_client';
    });

    // Verify audit log
    $this->assertDatabaseHas('dashboard_audit_events', [
        'event_type' => 'adapter.webhook.received',
    ]);
    $this->assertDatabaseHas('dashboard_audit_events', [
        'event_type' => 'adapter.webhook.completed',
    ]);
});

test('webhook targeted to specific agent_id in URL routes directly to that agent', function () {
    $customAgent = Agent::create([
        'agent_id' => (string) Str::uuid(),
        'name' => 'Agent-Specific',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', 'agent_sec_spec'),
        'last_heartbeat_at' => now(),
        'metadata_json' => ['api_url' => 'http://specific-agent:8000'],
    ]);

    Http::fake([
        'http://specific-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec-spec-1',
            'thread_id' => 'thread-spec-1',
            'status' => 'COMPLETED',
            'output' => 'Agente específico ejecutado.',
        ], 200),
    ]);

    $response = $this->postJson("/api/v1/adapters/webhook/{$customAgent->agent_id}", [
        'message' => 'Ejecutar tarea específica',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'output' => 'Agente específico ejecutado.',
        ]);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://specific-agent:8000/executions/trigger';
    });
});

test('GitLab CI/CD push webhook is synthesized and formatted as CI/CD response', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec-gitlab-01',
            'thread_id' => 'pipe-gitlab-01',
            'status' => 'COMPLETED',
            'output' => 'Análisis de commit: Sin vulnerabilidades detectadas.',
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/adapters/webhook', [
        'object_kind' => 'push',
        'ref' => 'refs/heads/release/v1.0',
        'user_name' => 'Developer',
        'project' => ['name' => 'core-payment'],
        'commits' => [['message' => 'refactor: mejorar validacion de tarjeta']],
        'format' => 'ci_cd',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'job_status' => 'COMPLETED',
            'pipeline_status' => 'PASSED',
            'summary' => 'Análisis de commit: Sin vulnerabilidades detectadas.',
        ]);

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        return str_contains($body['prompt'], 'GitLab CI/CD: Push')
            && str_contains($body['prompt'], 'core-payment')
            && $body['metadata']['source'] === 'gitlab';
    });
});

test('Chatbot stimulus is normalized and formatted as chatbot conversational response', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec-chat-01',
            'thread_id' => 'chat-session-777',
            'status' => 'COMPLETED',
            'output' => 'El clima para hoy es soleado.',
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/adapters/webhook', [
        'chat_id' => 'chat-session-777',
        'message' => [
            'text' => '¿Cuál es el pronóstico del tiempo?',
        ],
        'format' => 'chatbot',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'response' => 'El clima para hoy es soleado.',
            'text' => 'El clima para hoy es soleado.',
            'thread_id' => 'chat-session-777',
            'status' => 'completed',
            'success' => true,
        ]);

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        return $body['prompt'] === '¿Cuál es el pronóstico del tiempo?'
            && $body['thread_id'] === 'chat-session-777';
    });
});

test('asynchronous webhook request returns 202 Accepted with PENDING status', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec-async-1',
            'thread_id' => 'thread-async-1',
            'status' => 'PENDING',
            'message' => 'Tarea encolada en segundo plano.',
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/adapters/webhook', [
        'prompt' => 'Migrar base de datos masiva',
        'async' => true,
    ]);

    $response->assertStatus(202)
        ->assertJson([
            'status' => 'PENDING',
        ]);
});

test('webhook authentication rejects unauthorized requests when webhook_secret is configured', function () {
    Config::set('adapters.webhook_secret', 'secret-webhook-key-999');

    // 1. Missing secret
    $response = $this->postJson('/api/v1/adapters/webhook', [
        'prompt' => 'Comprobar balanceadores',
    ]);
    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
        ]);

    // 2. Invalid secret
    $responseInvalid = $this->withHeader('X-Webhook-Secret', 'wrong-secret')
        ->postJson('/api/v1/adapters/webhook', [
            'prompt' => 'Comprobar balanceadores',
        ]);
    $responseInvalid->assertStatus(401);

    // 3. Valid secret in header
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec-auth-1',
            'thread_id' => 'thread-auth-1',
            'status' => 'COMPLETED',
            'output' => 'OK',
        ], 200),
    ]);

    $responseValid = $this->withHeader('X-Webhook-Secret', 'secret-webhook-key-999')
        ->postJson('/api/v1/adapters/webhook', [
            'prompt' => 'Comprobar balanceadores',
        ]);
    $responseValid->assertStatus(200);
});

test('returns 404 when target agent does not exist', function () {
    $nonExistentId = (string) Str::uuid();

    $response = $this->postJson("/api/v1/adapters/webhook/{$nonExistentId}", [
        'prompt' => 'Probar agente fantasma',
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'error' => 'Agent Not Found',
        ]);
});

test('returns 400 when target agent is OFFLINE', function () {
    $offlineAgent = Agent::create([
        'agent_id' => (string) Str::uuid(),
        'name' => 'Agent-Offline',
        'status' => 'OFFLINE',
        'permanent_secret_hash' => hash('sha256', 'agent_sec_off'),
    ]);

    $response = $this->postJson("/api/v1/adapters/webhook/{$offlineAgent->agent_id}", [
        'prompt' => 'Probar agente apagado',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Agent Unavailable',
        ]);
});

test('returns 502 when upstream agent fails with connection error', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'detail' => 'Internal server error en runtime del agente',
        ], 500),
    ]);

    $response = $this->postJson('/api/v1/adapters/webhook', [
        'prompt' => 'Disparar ejecución fallida',
    ]);

    $response->assertStatus(502)
        ->assertJson([
            'error' => 'Agent Gateway Error',
        ]);
});

test('propagates and echoes X-Correlation-ID header', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec-corr-1',
            'thread_id' => 'thread-corr-1',
            'status' => 'COMPLETED',
            'output' => 'Correlación validada',
        ], 200),
    ]);

    $response = $this->withHeader('X-Correlation-ID', 'cid-webhook-trace-999')
        ->postJson('/api/v1/adapters/webhook', [
            'prompt' => 'Validar correlación',
        ]);

    $response->assertStatus(200)
        ->assertHeader('X-Correlation-ID', 'cid-webhook-trace-999');

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        return $request->header('X-Correlation-ID')[0] === 'cid-webhook-trace-999'
            && $body['correlation_id'] === 'cid-webhook-trace-999';
    });
});
