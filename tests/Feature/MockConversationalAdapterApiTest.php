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

    $this->conversationalAgent = Agent::create([
        'agent_id' => 'a0000000-0000-0000-0000-000000000001',
        'name' => 'Agent-Conversacional-Real',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', 'agent_sec_conv'),
        'last_heartbeat_at' => now(),
        'metadata_json' => ['api_url' => 'http://test-agent:8000'],
    ]);
});

test('POST /api/v1/adapters/mock-asterisk simulates voice call without requiring Asterisk PBX', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec-voice-ast-01',
            'thread_id' => 'call-uuid-999',
            'status' => 'COMPLETED',
            'output' => 'Bienvenido al servicio telefónico automático. Su saldo es positivo.',
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/adapters/mock-asterisk', [
        'call_id' => 'call-uuid-999',
        'caller_id' => '+34912345678',
        'caller_name' => 'Cliente Telefónico',
        'transcript' => '¿Cuál es mi saldo?',
        'channel' => 'SIP/trunk-core',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'simulation_type' => 'asterisk_voice',
            'call_id' => 'call-uuid-999',
            'playback_action' => 'PLAY_TTS',
            'tts_text' => 'Bienvenido al servicio telefónico automático. Su saldo es positivo.',
            'hangup' => false,
        ]);

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        return $body['prompt'] === '¿Cuál es mi saldo?'
            && $body['thread_id'] === 'call-uuid-999'
            && ($body['parameters']['simulation_type'] ?? null) === 'asterisk_voice';
    });

    // Check audit events
    $this->assertDatabaseHas('dashboard_audit_events', [
        'event_type' => 'adapter.mock_conversational.received',
    ]);
    $this->assertDatabaseHas('dashboard_audit_events', [
        'event_type' => 'adapter.mock_conversational.completed',
    ]);
});

test('POST /api/v1/adapters/mock-conversational simulates chat stimulus with conversation thread', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec-chat-web-01',
            'thread_id' => 'chat-thread-777',
            'status' => 'COMPLETED',
            'output' => 'Hola, soy el asistente virtual. ¿En qué puedo ayudarte?',
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/adapters/mock-conversational', [
        'chat_id' => 'chat-thread-777',
        'sender' => 'usuario_movil',
        'message' => 'Hola asistente',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'simulation_type' => 'chat',
            'chat_id' => 'chat-thread-777',
            'response' => 'Hola, soy el asistente virtual. ¿En qué puedo ayudarte?',
            'sender' => 'usuario_movil',
        ]);
});

test('POST /api/v1/adapters/mock-conversational with explicit agent_id targets that agent', function () {
    $customAgent = Agent::create([
        'agent_id' => (string) Str::uuid(),
        'name' => 'Agent-DevOps-Custom',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', 'agent_sec_dev'),
        'last_heartbeat_at' => now(),
        'metadata_json' => ['api_url' => 'http://devops-agent:8000'],
    ]);

    Http::fake([
        'http://devops-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec-devops-chat-1',
            'thread_id' => 'call-ops-1',
            'status' => 'COMPLETED',
            'output' => 'DevOps: Contenedores verificados.',
        ], 200),
    ]);

    $response = $this->postJson("/api/v1/adapters/mock-conversational/{$customAgent->agent_id}", [
        'call_id' => 'call-ops-1',
        'caller_id' => 'operator_internal',
        'transcript' => 'Chequear contenedores',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'tts_text' => 'DevOps: Contenedores verificados.',
        ]);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://devops-agent:8000/executions/trigger';
    });
});

test('returns 404 when target agent in mock conversational does not exist', function () {
    $ghostId = (string) Str::uuid();

    $response = $this->postJson("/api/v1/adapters/mock-conversational/{$ghostId}", [
        'transcript' => 'Hola',
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'error' => 'Agent Not Found',
        ]);
});

test('returns 400 when target agent is OFFLINE', function () {
    $offlineAgent = Agent::create([
        'agent_id' => (string) Str::uuid(),
        'name' => 'Agent-Offline-Voice',
        'status' => 'OFFLINE',
        'permanent_secret_hash' => hash('sha256', 'agent_sec_off'),
    ]);

    $response = $this->postJson("/api/v1/adapters/mock-conversational/{$offlineAgent->agent_id}", [
        'transcript' => 'Llamada a agente apagado',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Agent Unavailable',
        ]);
});

test('returns 502 when upstream agent fails', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'detail' => 'Internal agent error',
        ], 500),
    ]);

    $response = $this->postJson('/api/v1/adapters/mock-asterisk', [
        'transcript' => 'Llamada con fallo',
    ]);

    $response->assertStatus(502)
        ->assertJson([
            'error' => 'Agent Gateway Error',
        ]);
});

test('propagates and echoes X-Correlation-ID header', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec-corr-voice',
            'thread_id' => 'call-corr-1',
            'status' => 'COMPLETED',
            'output' => 'OK',
        ], 200),
    ]);

    $response = $this->withHeader('X-Correlation-ID', 'cid-telephony-trace-777')
        ->postJson('/api/v1/adapters/mock-asterisk', [
            'transcript' => 'Comprobar correlación',
        ]);

    $response->assertStatus(200)
        ->assertHeader('X-Correlation-ID', 'cid-telephony-trace-777');
});
