<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\Agents\AgentHttpClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class McpServerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected ApiToken $apiTokenModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'MCP Admin',
            'email' => 'mcp-admin@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);

        $generated = ApiToken::generate('MCP Full Token', $this->user, ['mcp:read', 'mcp:execute']);
        $this->token = $generated['token'];
        $this->apiTokenModel = $generated['model'];
    }

    public function test_initialize_handshake_returns_mcp_protocol_version(): void
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => 'req-init-1',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => (object) [],
                'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/mcp', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'jsonrpc' => '2.0',
                'id' => 'req-init-1',
                'result' => [
                    'protocolVersion' => '2024-11-05',
                    'serverInfo' => [
                        'name' => 'jg_dashboard-mcp',
                    ],
                ],
            ]);
    }

    public function test_tools_list_namespaces_and_excludes_high_risk_tools(): void
    {
        Agent::create([
            'agent_id' => 'a0000000-0000-0000-0000-000000000001',
            'name' => 'Conversational Agent',
            'status' => 'ACTIVE',
            'capabilities_json' => [
                'tools' => [
                    [
                        'name' => 'get_weather',
                        'description' => 'Consulta el clima',
                        'risk_level' => 'low',
                        'read_only' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => ['city' => ['type' => 'string']],
                        ],
                    ],
                    [
                        'name' => 'delete_user',
                        'description' => 'Elimina un usuario',
                        'risk_level' => 'high',
                        'read_only' => false,
                    ],
                ],
            ],
        ]);

        $payload = [
            'jsonrpc' => '2.0',
            'id' => 'req-list-1',
            'method' => 'tools/list',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/mcp', $payload);

        $response->assertStatus(200);
        $tools = $response->json('result.tools');

        $this->assertCount(1, $tools);
        $this->assertEquals('conversational_agent__get_weather', $tools[0]['name']);
        $this->assertEquals('Consulta el clima', $tools[0]['description']);
        $this->assertEquals('string', $tools[0]['inputSchema']['properties']['city']['type']);
    }

    public function test_tools_call_executes_tool_via_mocked_http_client(): void
    {
        $agent = Agent::create([
            'agent_id' => 'a0000000-0000-0000-0000-000000000002',
            'name' => 'DevOps Agent',
            'status' => 'ACTIVE',
            'capabilities_json' => [
                'tools' => [
                    [
                        'name' => 'inspect_container',
                        'description' => 'Inspecciona contenedor',
                        'risk_level' => 'low',
                        'read_only' => true,
                    ],
                ],
            ],
            'metadata_json' => ['api_url' => 'http://jg_agente_devops:8000'],
        ]);

        $mockClient = Mockery::mock(AgentHttpClient::class);
        $mockClient->shouldReceive('sendRequest')
            ->once()
            ->with('POST', '/tools/inspect_container/execute', Mockery::on(function ($data) {
                return $data['arguments']['container_id'] === 'c123';
            }))
            ->andReturn([
                'success' => true,
                'status_code' => 200,
                'data' => [
                    'tool_name' => 'inspect_container',
                    'success' => true,
                    'output' => ['status' => 'running', 'image' => 'nginx:alpine'],
                ],
            ]);

        $this->app->bind(AgentHttpClient::class, fn () => $mockClient);

        $payload = [
            'jsonrpc' => '2.0',
            'id' => 'req-call-1',
            'method' => 'tools/call',
            'params' => [
                'name' => 'devops_agent__inspect_container',
                'arguments' => ['container_id' => 'c123'],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/mcp', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'jsonrpc' => '2.0',
                'id' => 'req-call-1',
                'result' => [
                    'isError' => false,
                ],
            ]);

        $content = $response->json('result.content.0.text');
        $this->assertStringContainsString('running', $content);
    }

    public function test_anti_dns_rebinding_blocks_unauthorized_origins(): void
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => 'req-rebinding',
            'method' => 'ping',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->withHeader('Origin', 'http://malicious-attacker-site.com')
            ->postJson('/api/v1/mcp', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'jsonrpc' => '2.0',
                'id' => 'req-rebinding',
                'error' => [
                    'code' => -32001,
                ],
            ]);
    }

    public function test_tools_call_forbidden_without_execute_scope(): void
    {
        $readOnlyToken = ApiToken::generate('Read Only', $this->user, ['mcp:read']);

        $payload = [
            'jsonrpc' => '2.0',
            'id' => 'req-call-scope',
            'method' => 'tools/call',
            'params' => [
                'name' => 'any__tool',
                'arguments' => [],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$readOnlyToken['token']}")
            ->postJson('/api/v1/mcp', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'jsonrpc' => '2.0',
                'id' => 'req-call-scope',
                'error' => [
                    'code' => -32000,
                ],
            ]);
    }

    public function test_unknown_rpc_method_returns_standard_error(): void
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => 'req-unknown',
            'method' => 'non_existent_method',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/mcp', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'jsonrpc' => '2.0',
                'id' => 'req-unknown',
                'error' => [
                    'code' => -32601,
                ],
            ]);
    }
}
