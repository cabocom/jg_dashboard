<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace Tests\Feature;

use App\Filament\Pages\TopologyMap;
use App\Models\InfraTopology;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TopologyMapTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'SRE Operator',
            'email' => 'operator@devops60k.local',
            'password' => Hash::make('password123'),
            'role' => 'operator',
        ]);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/admin/topology-map');
        $response->assertRedirect('/admin/login');
    }

    public function test_authenticated_user_can_access_topology_map(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(TopologyMap::getUrl());
        $response->assertStatus(200);
        $response->assertSee('Mapa de Topología');
    }

    public function test_topology_map_renders_database_snapshot(): void
    {
        $this->actingAs($this->user);

        InfraTopology::create([
            'target' => 'current',
            'nodes_json' => [
                ['id' => 'host', 'label' => 'Host Node', 'type' => 'host', 'status' => 'healthy'],
                ['id' => 'custom_worker', 'label' => 'Custom Worker', 'type' => 'container', 'status' => 'down', 'metadata' => ['image' => 'custom:v1']],
            ],
            'edges_json' => [
                ['from' => 'host', 'to' => 'custom_worker', 'relation' => 'contains'],
            ],
            'updated_at' => now(),
        ]);

        $response = $this->get(TopologyMap::getUrl());
        $response->assertStatus(200);
        $response->assertSee('Host Node');
        $response->assertSee('Custom Worker');
    }

    public function test_livewire_component_selection_and_refresh(): void
    {
        $this->actingAs($this->user);

        InfraTopology::create([
            'target' => 'current',
            'nodes_json' => [
                ['id' => 'test_node_1', 'label' => 'Test Node One', 'type' => 'container', 'status' => 'healthy', 'metadata' => ['cpu' => '12%']],
            ],
            'edges_json' => [],
            'updated_at' => now(),
        ]);

        Livewire::test(TopologyMap::class)
            ->assertStatus(200)
            ->call('refreshTopology')
            ->assertSet('totalNodes', 1)
            ->assertSet('healthyNodes', 1)
            ->call('selectNode', 'test_node_1')
            ->assertSet('selectedNode.id', 'test_node_1')
            ->call('closeNodeDetails')
            ->assertSet('selectedNode', null);
    }
}
