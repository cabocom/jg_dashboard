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

use App\Filament\Pages\TopologyMap;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TopologyMapMcpTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Operator SRE',
            'email' => 'sre-mcp@devops60k.local',
            'password' => Hash::make('secret123'),
            'role' => 'operator',
        ]);
    }

    public function test_dynamic_topology_renders_mcp_sidecar_nodes_and_edges(): void
    {
        $this->actingAs($this->user);

        // Agente con sidecar MCP conectado en metrics_json
        $agent = Agent::create([
            'agent_id' => 'agent_with_mcp',
            'name' => 'Agent With MCP',
            'type' => 'conversational',
            'status' => 'ACTIVE',
            'version' => '2.0.0',
            'last_heartbeat_at' => now(),
            'metrics_json' => [
                'mcp_sidecars' => [
                    'filesystem' => [
                        'status' => 'connected',
                        'url' => 'http://mcp-filesystem:8080/mcp',
                        'tools_count' => 3,
                        'schema_hash' => 'hash_fs_123',
                    ],
                    'git' => [
                        'status' => 'disconnected',
                        'url' => 'http://mcp-git:8080/mcp',
                        'tools_count' => 5,
                        'schema_hash' => 'hash_git_456',
                    ],
                ],
            ],
            'capabilities_json' => [],
        ]);

        $test = Livewire::test(TopologyMap::class)
            ->call('refreshTopology')
            ->assertStatus(200);

        $nodes = $test->get('nodes');
        $edges = $test->get('edges');

        // Verificar nodos MCP
        $fsNode = collect($nodes)->firstWhere('id', 'mcp_agent_with_mcp_filesystem');
        $this->assertNotNull($fsNode, 'El nodo MCP filesystem debe existir');
        $this->assertSame('mcp_server', $fsNode['type']);
        $this->assertSame('healthy', $fsNode['status']);
        $this->assertSame('hash_fs_123', $fsNode['metadata']['schema_hash']);
        $this->assertSame(3, $fsNode['metadata']['tools_count']);

        $gitNode = collect($nodes)->firstWhere('id', 'mcp_agent_with_mcp_git');
        $this->assertNotNull($gitNode, 'El nodo MCP git debe existir');
        $this->assertSame('mcp_server', $gitNode['type']);
        $this->assertSame('down', $gitNode['status']);

        // Verificar aristas MCP
        $fsEdge = collect($edges)->firstWhere('to', 'mcp_agent_with_mcp_filesystem');
        $this->assertNotNull($fsEdge, 'La arista hacia el MCP filesystem debe existir');
        $this->assertSame('agent_with_mcp', $fsEdge['from']);
        $this->assertSame('mcp_streamable_http', $fsEdge['relation']);

        // Verificar selección de nodo
        $test->call('selectNode', 'mcp_agent_with_mcp_filesystem')
            ->assertSet('selectedNode.id', 'mcp_agent_with_mcp_filesystem')
            ->assertSet('selectedNode.metadata.schema_hash', 'hash_fs_123');
    }

    public function test_mcp_nodes_from_capabilities_json_fallback(): void
    {
        $this->actingAs($this->user);

        // Agente con mcp_servers en capabilities_json en lugar de metrics_json
        Agent::create([
            'agent_id' => 'agent_fallback_mcp',
            'name' => 'Agent Fallback MCP',
            'type' => 'conversational',
            'status' => 'ACTIVE',
            'version' => '2.0.0',
            'last_heartbeat_at' => now(),
            'metrics_json' => [],
            'capabilities_json' => [
                'mcp_servers' => [
                    [
                        'name' => 'database_mcp',
                        'status' => 'connected',
                        'endpoint' => 'http://mcp-db:8080/mcp',
                        'tools_count' => 8,
                        'schema_hash' => 'hash_db_789',
                    ],
                ],
            ],
        ]);

        $test = Livewire::test(TopologyMap::class)
            ->call('refreshTopology')
            ->assertStatus(200);

        $nodes = $test->get('nodes');
        $dbNode = collect($nodes)->firstWhere('id', 'mcp_agent_fallback_mcp_database_mcp');
        $this->assertNotNull($dbNode);
        $this->assertSame('mcp_server', $dbNode['type']);
        $this->assertSame('healthy', $dbNode['status']);
        $this->assertSame('http://mcp-db:8080/mcp', $dbNode['metadata']['url']);
    }
}
