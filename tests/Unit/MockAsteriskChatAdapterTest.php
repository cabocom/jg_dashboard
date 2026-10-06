<?php

use App\Adapters\Conversational\MockAsteriskChatAdapter;
use App\Models\Agent;
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
        'name' => 'Agent-Conversacional-Test',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', 'agent_sec_conv'),
        'last_heartbeat_at' => now(),
        'metadata_json' => ['api_url' => 'http://test-agent:8000'],
    ]);

    $this->adapter = new MockAsteriskChatAdapter();
});

test('transforms simulated Asterisk call into AgentInput and serializes TTS playback output', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec-voice-123',
            'thread_id' => 'call-ast-9988',
            'status' => 'COMPLETED',
            'output' => 'Hola Javier, el servicio de red está funcionando perfectamente.',
            'cognitive_steps' => [['step' => 1]],
        ], 200),
    ]);

    $stimulus = [
        'call_id' => 'call-ast-9988',
        'caller_id' => '+34600123456',
        'caller_name' => 'Javier Gálvez',
        'transcript' => '¿Cuál es el estado de la conexión a Internet?',
        'channel' => 'SIP/trunk-madrid-01',
    ];

    $result = $this->adapter->handle($stimulus);

    expect($result['http_code'])->toBe(200)
        ->and($result['body']['status'])->toBe('success')
        ->and($result['body']['simulation_type'])->toBe('asterisk_voice')
        ->and($result['body']['call_id'])->toBe('call-ast-9988')
        ->and($result['body']['tts_text'])->toBe('Hola Javier, el servicio de red está funcionando perfectamente.')
        ->and($result['body']['playback_action'])->toBe('PLAY_TTS')
        ->and($result['body']['hangup'])->toBeFalse()
        ->and($result['body']['transcript_turn']['input'])->toBe('¿Cuál es el estado de la conexión a Internet?');

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        return $body['prompt'] === '¿Cuál es el estado de la conexión a Internet?'
            && $body['thread_id'] === 'call-ast-9988'
            && ($body['parameters']['is_telephony'] ?? null) === true
            && $body['user_context']['username'] === 'Javier Gálvez';
    });
});

test('transforms simulated Chat message into AgentInput and serializes conversational chat reply', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec-chat-456',
            'thread_id' => 'sess-chat-001',
            'status' => 'COMPLETED',
            'output' => 'Claro, aquí tienes la información solicitada.',
        ], 200),
    ]);

    $stimulus = [
        'chat_id' => 'sess-chat-001',
        'sender' => 'usuario_web',
        'message' => 'Necesito consultar el saldo de la cuenta',
    ];

    $result = $this->adapter->handle($stimulus);

    expect($result['http_code'])->toBe(200)
        ->and($result['body']['simulation_type'])->toBe('chat')
        ->and($result['body']['chat_id'])->toBe('sess-chat-001')
        ->and($result['body']['response'])->toBe('Claro, aquí tienes la información solicitada.')
        ->and($result['body']['conversation']['is_final'])->toBeTrue();
});
