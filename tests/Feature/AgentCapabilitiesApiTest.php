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
        'name' => 'Admin Cap',
        'email' => 'admin_cap@test.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
    ]);

    $this->operatorUser = User::create([
        'name' => 'Operator Cap',
        'email' => 'operator_cap@test.com',
        'password' => Hash::make('password123'),
        'role' => 'operator',
    ]);

    $this->viewerUser = User::create([
        'name' => 'Viewer Cap',
        'email' => 'viewer_cap@test.com',
        'password' => Hash::make('password123'),
        'role' => 'viewer',
    ]);

    $this->adminToken = $this->jwtService->generateToken($this->adminUser);
    $this->operatorToken = $this->jwtService->generateToken($this->operatorUser);
    $this->viewerToken = $this->jwtService->generateToken($this->viewerUser);

    $this->activeAgent = Agent::create([
        'agent_id' => (string) Str::uuid(),
        'name' => 'Agent-Core-Capabilities',
        'status' => 'ACTIVE',
        'version' => '1.2.0',
        'permanent_secret_hash' => hash('sha256', 'agent_sec_cap_123'),
        'last_heartbeat_at' => now(),
        'metadata_json' => ['api_url' => 'http://test-agent:8000'],
        'capabilities_json' => ['tools' => ['InitialTool']],
    ]);
});

test('unauthenticated request to GET /api/v1/agents/{agent_id}/capabilities returns 401 unauthorized', function () {
    $response = $this->getJson("/api/v1/agents/{$this->activeAgent->agent_id}/capabilities");

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
        ]);
});

test('returns 404 when requested agent_id does not exist', function () {
    $nonExistentId = (string) Str::uuid();

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->viewerToken)
        ->getJson("/api/v1/agents/{$nonExistentId}/capabilities");

    $response->assertStatus(404)
        ->assertJson([
            'error' => 'Not Found',
        ]);
});

test('returns 400 when agent is in PENDING registration state', function () {
    $pendingAgent = Agent::create([
        'agent_id' => (string) Str::uuid(),
        'name' => 'Agent-Pending-Cap',
        'status' => 'PENDING',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->operatorToken)
        ->getJson("/api/v1/agents/{$pendingAgent->agent_id}/capabilities");

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Agent Pending Registration',
        ]);
});

test('operator role can query capabilities and syncs local Agent model', function () {
    Http::fake([
        'http://test-agent:8000/capabilities' => Http::response([
            'agent_id' => $this->activeAgent->agent_id,
            'tools' => ['WeatherTool', 'KubernetesTool', 'NetworkTool'],
            'engines' => ['langgraph_core', 'planner_engine'],
            'plugins' => [
                'weather_plugin' => ['version' => '1.0.0', 'status' => 'active'],
                'k8s_plugin' => ['version' => '2.1.0', 'status' => 'active'],
            ],
            'total_tools' => 3,
        ], 200),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->operatorToken)
        ->getJson("/api/v1/agents/{$this->activeAgent->agent_id}/capabilities");

    $response->assertStatus(200)
        ->assertJson([
            'agent_id' => $this->activeAgent->agent_id,
            'name' => 'Agent-Core-Capabilities',
            'status' => 'ACTIVE',
            'version' => '1.2.0',
            'capabilities' => [
                'tools' => ['WeatherTool', 'KubernetesTool', 'NetworkTool'],
                'total_tools' => 3,
            ],
        ]);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://test-agent:8000/capabilities' &&
            $request->method() === 'GET' &&
            $request->hasHeader('X-Control-Plane', 'jg_dashboard') &&
            $request->hasHeader('X-Signature');
    });

    $this->activeAgent->refresh();
    expect($this->activeAgent->capabilities_json['tools'])->toContain('WeatherTool', 'KubernetesTool');
});

test('admin and viewer roles can also inspect agent capabilities', function () {
    Http::fake([
        'http://test-agent:8000/capabilities' => Http::response([
            'tools' => ['SystemDiagnosticsTool'],
            'engines' => ['standard'],
        ], 200),
    ]);

    $respAdmin = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
        ->getJson("/api/v1/agents/{$this->activeAgent->agent_id}/capabilities");
    $respAdmin->assertStatus(200)->assertJsonPath('capabilities.tools.0', 'SystemDiagnosticsTool');

    $respViewer = $this->withHeader('Authorization', 'Bearer ' . $this->viewerToken)
        ->getJson("/api/v1/agents/{$this->activeAgent->agent_id}/capabilities");
    $respViewer->assertStatus(200)->assertJsonPath('capabilities.tools.0', 'SystemDiagnosticsTool');
});

test('handles network failure or unreachable agent with 502/503 and structured error', function () {
    Http::fake([
        'http://test-agent:8000/*' => function () {
            throw new ConnectionException('Connection timed out connecting to agent capabilities');
        },
    ]);

    $response = $this->withHeader('Authorization', 'Bearer ' . $this->operatorToken)
        ->getJson("/api/v1/agents/{$this->activeAgent->agent_id}/capabilities");

    $response->assertStatus(504)
        ->assertJson([
            'error' => 'Agent Gateway Error',
            'agent_id' => $this->activeAgent->agent_id,
        ]);
});

test('unversioned route /api/agents/{agent_id}/capabilities returns 404 not found (CWE-668)', function () {
    $this->withHeader('Authorization', 'Bearer ' . $this->operatorToken)
        ->getJson("/api/agents/{$this->activeAgent->agent_id}/capabilities")
        ->assertStatus(404);
});
