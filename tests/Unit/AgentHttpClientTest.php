<?php

use App\Models\Agent;
use App\Services\Agents\AgentHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    Config::set('agent.base_url', 'http://test-agent:8000');
    Config::set('agent.internal_secret', 'test_secret_key_123');
    Config::set('agent.timeout', 5);
    Config::set('agent.retry_times', 2);
    Config::set('agent.retry_sleep_ms', 10);
});

test('generateSignatureHeaders generates valid HMAC-SHA256 signature and security headers', function () {
    $client = new AgentHttpClient();
    $body = json_encode(['prompt' => 'Test execution']);
    
    $headers = $client->generateSignatureHeaders($body, 'POST', '/executions/trigger');

    expect($headers)->toHaveKeys([
        'Accept',
        'User-Agent',
        'X-Control-Plane',
        'X-Timestamp',
        'X-Signature',
        'X-Correlation-ID',
    ]);

    expect($headers)->not->toHaveKey('X-Control-Plane-Secret');
    expect($headers['X-Control-Plane'])->toBe('jg_dashboard');

    $bodyHash = hash('sha256', $body);
    $canonicalString = "POST\n/executions/trigger\n" . $headers['X-Timestamp'] . "\n" . $bodyHash;
    $expectedSignature = hash_hmac('sha256', $canonicalString, 'test_secret_key_123');
    expect($headers['X-Signature'])->toBe($expectedSignature);
});

test('forAgent factory configures agent_id and custom api_url if present in metadata', function () {
    $agent = new Agent([
        'agent_id' => (string) Str::uuid(),
        'name' => 'Cluster-Worker-01',
        'metadata_json' => ['api_url' => 'http://worker-cluster.internal:9000/'],
    ]);

    $client = AgentHttpClient::forAgent($agent);

    expect($client->getAgentId())->toBe($agent->agent_id);
    expect($client->getBaseUrl())->toBe('http://worker-cluster.internal:9000');

    $headers = $client->generateSignatureHeaders();
    expect($headers['X-Agent-ID'])->toBe($agent->agent_id);
});

test('checkHealth returns success when agent responds with 200', function () {
    Http::fake([
        'http://test-agent:8000/health' => Http::response([
            'status' => 'healthy',
            'components' => ['db' => ['status' => 'connected']],
        ], 200),
    ]);

    $client = new AgentHttpClient();
    $result = $client->checkHealth();

    expect($result['success'])->toBeTrue();
    expect($result['status_code'])->toBe(200);
    expect($result['data']['status'])->toBe('healthy');
    expect($result['latency_ms'])->toBeGreaterThanOrEqual(0);
});

test('checkHealth falls back to root endpoint when /health returns 404', function () {
    Http::fake([
        'http://test-agent:8000/health' => Http::response(['detail' => 'Not Found'], 404),
        'http://test-agent:8000/' => Http::response([
            'service' => 'jg_agente',
            'status' => 'online',
        ], 200),
    ]);

    $client = new AgentHttpClient();
    $result = $client->checkHealth();

    expect($result['success'])->toBeTrue();
    expect($result['status_code'])->toBe(200);
    expect($result['data']['service'])->toBe('jg_agente');
});

test('getCapabilities retrieves tools and engines inventory', function () {
    Http::fake([
        'http://test-agent:8000/capabilities' => Http::response([
            'agent_id' => 'agent-01',
            'tools' => ['WeatherTool', 'KubernetesTool'],
            'plugins' => ['weather_plugin'],
        ], 200),
    ]);

    $client = new AgentHttpClient();
    $result = $client->getCapabilities();

    expect($result['success'])->toBeTrue();
    expect($result['data']['tools'])->toContain('WeatherTool');
});

test('triggerExecution sends signed AgentInput payload to /executions/trigger', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'execution_id' => 'exec_123',
            'thread_id' => 'thread_abc',
            'status' => 'completed',
            'final_output' => 'Task done',
        ], 200),
    ]);

    $client = new AgentHttpClient();
    $payload = [
        'user_prompt' => 'Check cluster nodes',
        'user_id' => 'usr_admin',
        'thread_id' => 'thread_abc',
    ];

    $result = $client->triggerExecution($payload);

    expect($result['success'])->toBeTrue();
    expect($result['data']['execution_id'])->toBe('exec_123');

    Http::assertSent(function (Request $request) use ($payload) {
        return $request->url() === 'http://test-agent:8000/executions/trigger' &&
            $request->method() === 'POST' &&
            $request->hasHeader('X-Control-Plane', 'jg_dashboard') &&
            $request->hasHeader('X-Signature') &&
            $request['user_prompt'] === 'Check cluster nodes';
    });
});

test('getExecutionStatus inspects thread execution trace', function () {
    Http::fake([
        'http://test-agent:8000/executions/thread_999' => Http::response([
            'thread_id' => 'thread_999',
            'status' => 'waiting_approval',
            'requires_approval' => true,
        ], 200),
    ]);

    $client = new AgentHttpClient();
    $result = $client->getExecutionStatus('thread_999');

    expect($result['success'])->toBeTrue();
    expect($result['data']['thread_id'])->toBe('thread_999');
    expect($result['data']['requires_approval'])->toBeTrue();
});

test('approveExecution and rejectExecution send HITL resolution requests', function () {
    Http::fake([
        'http://test-agent:8000/executions/thread_1/approve' => Http::response([
            'status' => 'completed',
            'approved' => true,
        ], 200),
        'http://test-agent:8000/executions/thread_2/reject' => Http::response([
            'status' => 'cancelled',
            'approved' => false,
        ], 200),
    ]);

    $client = new AgentHttpClient();

    $approveRes = $client->approveExecution('thread_1', ['approver_id' => 'usr_admin', 'reason' => 'Approved']);
    expect($approveRes['success'])->toBeTrue();
    expect($approveRes['data']['status'])->toBe('completed');

    $rejectRes = $client->rejectExecution('thread_2', ['rejector_id' => 'usr_admin', 'reason' => 'Risk high']);
    expect($rejectRes['success'])->toBeTrue();
    expect($rejectRes['data']['status'])->toBe('cancelled');
});

test('client gracefully handles HTTP 500 errors without throwing fatal uncaught exceptions', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response([
            'detail' => 'Internal server error inside cognitive graph',
        ], 500),
    ]);

    $client = new AgentHttpClient();
    $result = $client->triggerExecution(['user_prompt' => 'Crash task']);

    expect($result['success'])->toBeFalse();
    expect($result['status_code'])->toBe(500);
    expect($result['error'])->toContain('HTTP 500');
});

test('client handles connection network failure gracefully', function () {
    Http::fake([
        'http://test-agent:8000/*' => function () {
            throw new ConnectionException('Could not resolve host: test-agent');
        },
    ]);

    $client = new AgentHttpClient();
    $result = $client->checkHealth();

    expect($result['success'])->toBeFalse();
    expect($result['status_code'])->toBe(503);
    expect($result['error'])->toContain('Agente inaccesible o fallo de red');
});

test('client handles connection timeout gracefully', function () {
    Http::fake([
        'http://test-agent:8000/*' => function () {
            throw new ConnectionException('Operation timed out after 5000 milliseconds');
        },
    ]);

    $client = new AgentHttpClient();
    $result = $client->checkHealth();

    expect($result['success'])->toBeFalse();
    expect($result['status_code'])->toBe(504);
    expect($result['error'])->toContain('Timeout al conectar con el agente');
});

test('client automatically retries on 5xx errors and succeeds if second attempt passes', function () {
    $attempt = 0;
    Http::fake([
        'http://test-agent:8000/health' => function () use (&$attempt) {
            $attempt++;
            if ($attempt === 1) {
                return Http::response(['detail' => 'Service Unavailable'], 503);
            }
            return Http::response(['status' => 'healthy'], 200);
        },
    ]);

    $client = new AgentHttpClient();
    $result = $client->checkHealth();

    expect($attempt)->toBe(2);
    expect($result['success'])->toBeTrue();
    expect($result['status_code'])->toBe(200);
});
