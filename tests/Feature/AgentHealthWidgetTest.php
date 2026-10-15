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

use App\Filament\Widgets\AgentHealthWidget;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AgentHealthWidgetTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('agent.base_url', 'http://test-agent:8000');
        Config::set('agent.internal_secret', 'internal_secret_test');

        $this->admin = User::create([
            'name' => 'Admin SRE',
            'email' => 'admin_widget@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);
    }

    public function test_widget_displays_online_status_when_agent_http_client_healthcheck_succeeds(): void
    {
        $this->actingAs($this->admin);

        Http::fake([
            'http://test-agent:8000/health' => Http::response([
                'status' => 'healthy',
                'version' => '1.0.0',
            ], 200),
        ]);

        Agent::create([
            'agent_id' => 'agent-widget-01',
            'name' => 'Agent Alpha',
            'status' => 'ACTIVE',
            'last_heartbeat_at' => now()->subSeconds(20),
        ]);

        Livewire::test(AgentHealthWidget::class)
            ->assertSuccessful()
            ->assertSee('Motor jg_agente (Execution Plane)')
            ->assertSee('Online / Healthy')
            ->assertSee('1 / 1 activos');
    }

    public function test_widget_displays_inaccessible_status_when_agent_is_unreachable(): void
    {
        $this->actingAs($this->admin);

        Http::fake([
            'http://test-agent:8000/health' => Http::response(null, 503),
            'http://test-agent:8000/' => Http::response(null, 503),
        ]);

        Livewire::test(AgentHealthWidget::class)
            ->assertSuccessful()
            ->assertSee('Motor jg_agente (Execution Plane)')
            ->assertSee('Inaccesible');
    }

    public function test_widget_counts_only_healthy_active_agents_in_stats(): void
    {
        $this->actingAs($this->admin);

        Http::fake([
            'http://test-agent:8000/health' => Http::response(['status' => 'healthy'], 200),
        ]);

        // 1. Healthy active agent
        Agent::create([
            'agent_id' => 'agent-healthy',
            'name' => 'Agent Healthy',
            'status' => 'ACTIVE',
            'last_heartbeat_at' => now()->subSeconds(10),
        ]);

        // 2. Stale active agent (> 90s without heartbeat)
        Agent::create([
            'agent_id' => 'agent-stale',
            'name' => 'Agent Stale',
            'status' => 'ACTIVE',
            'last_heartbeat_at' => now()->subSeconds(150),
        ]);

        // 3. Offline agent
        Agent::create([
            'agent_id' => 'agent-off',
            'name' => 'Agent Offline',
            'status' => 'OFFLINE',
        ]);

        Livewire::test(AgentHealthWidget::class)
            ->assertSuccessful()
            ->assertSee('1 / 3 activos');
    }
}
