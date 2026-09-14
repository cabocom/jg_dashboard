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

test('forAgent decrypts permanent_secret_encrypted and computes valid HMAC signature for dynamic agent', function () {
    $dynamicSecret = 'sec_dynamic_custom_worker_random_token_9999999999';
    $agent = Agent::create([
        'name' => 'Dynamic-Scale-Worker',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', $dynamicSecret),
        'permanent_secret_encrypted' => $dynamicSecret,
        'metadata_json' => ['api_url' => 'http://dynamic-agent:8000'],
    ]);

    $client = AgentHttpClient::forAgent($agent);

    $body = json_encode(['prompt' => 'ping test']);
    $headers = $client->generateSignatureHeaders($body, 'POST', '/executions/trigger');

    $bodyHash = hash('sha256', $body);
    $canonicalString = "POST\n/executions/trigger\n" . $headers['X-Timestamp'] . "\n" . $bodyHash;
    $expectedSignature = hash_hmac('sha256', $canonicalString, $dynamicSecret);

    expect($headers['X-Signature'])->toBe($expectedSignature);
});

test('forAgent does not inherit devops_secret simply because name contains devops (DASH-SEC-05)', function () {
    Config::set('agent.internal_secret', 'general_internal_secret_999');
    Config::set('agent.devops_secret', 'super_secret_devops_prod_key_777');

    // Agente con subcadena "devops" en el nombre pero con UUID arbitrario
    $agent = new Agent([
        'agent_id' => 'b1111111-1111-1111-1111-111111111111',
        'name' => 'test-devops-sandbox',
    ]);

    $client = AgentHttpClient::forAgent($agent);

    $body = json_encode(['action' => 'deploy']);
    $headers = $client->generateSignatureHeaders($body, 'POST', '/commands/test');

    $bodyHash = hash('sha256', $body);
    $canonicalString = "POST\n/commands/test\n" . $headers['X-Timestamp'] . "\n" . $bodyHash;
    
    $devopsSignature = hash_hmac('sha256', $canonicalString, 'super_secret_devops_prod_key_777');
    $generalSignature = hash_hmac('sha256', $canonicalString, 'general_internal_secret_999');

    // NO debe firmar con el secreto de producción DevOps por simple coincidencia de nombre
    expect($headers['X-Signature'])->not->toBe($devopsSignature);
    expect($headers['X-Signature'])->toBe($generalSignature);
});

test('forAgent does not inherit conversational secret simply because name contains conversacional (DASH-SEC-05)', function () {
    Config::set('agent.internal_secret', 'conversational_secret_custom_123');

    $agent = new Agent([
        'agent_id' => 'c2222222-2222-2222-2222-222222222222',
        'name' => 'agente-conversacional-ficticio',
        'permanent_secret_encrypted' => 'agent_specific_encrypted_secret_456',
    ]);

    $client = AgentHttpClient::forAgent($agent);

    $body = json_encode(['msg' => 'hola']);
    $headers = $client->generateSignatureHeaders($body, 'POST', '/chat');

    $bodyHash = hash('sha256', $body);
    $canonicalString = "POST\n/chat\n" . $headers['X-Timestamp'] . "\n" . $bodyHash;

    $expectedSignature = hash_hmac('sha256', $canonicalString, 'agent_specific_encrypted_secret_456');
    $genericSignature = hash_hmac('sha256', $canonicalString, 'conversational_secret_custom_123');

    expect($headers['X-Signature'])->toBe($expectedSignature);
    expect($headers['X-Signature'])->not->toBe($genericSignature);
});

test('forAgent strictly resolves devops_secret for canonical DevOps UUID a0000000-0000-0000-0000-000000000002', function () {
    Config::set('agent.devops_secret', 'canonical_devops_secret_888');

    $agent = new Agent([
        'agent_id' => 'a0000000-0000-0000-0000-000000000002',
        'name' => 'Custom Unrelated Name',
    ]);

    $client = AgentHttpClient::forAgent($agent);

    $body = json_encode(['task' => 'status']);
    $headers = $client->generateSignatureHeaders($body, 'POST', '/status');

    $bodyHash = hash('sha256', $body);
    $canonicalString = "POST\n/status\n" . $headers['X-Timestamp'] . "\n" . $bodyHash;
    $expectedSignature = hash_hmac('sha256', $canonicalString, 'canonical_devops_secret_888');

    expect($headers['X-Signature'])->toBe($expectedSignature);
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

test('client uses 120s default timeout and supports withTimeout fluent modifier', function () {
    // Cuando no hay config explícito o se usa el default de config/agent.php
    Config::set('agent.timeout', null);
    $defaultClient = new AgentHttpClient();
    expect($defaultClient->getTimeout())->toBe(120);

    // Fluent modifier withTimeout
    $customClient = $defaultClient->withTimeout(90);
    expect($customClient->getTimeout())->toBe(90);
});
