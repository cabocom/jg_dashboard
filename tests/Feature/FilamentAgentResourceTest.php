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
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->adminUser = User::create([
        'name' => 'Agent Admin',
        'email' => 'agentadmin@devops60k.local',
        'password' => Hash::make('secret_admin_pass'),
        'role' => 'admin',
    ]);
});

test('admin user can access /admin/agents list in Filament', function () {
    Agent::create([
        'agent_id' => 'agent-test-01',
        'name' => 'Agent Core SRE',
        'status' => 'ACTIVE',
        'version' => '0.1.0',
    ]);

    $response = $this->actingAs($this->adminUser)->get('/admin/agents');

    $response->assertStatus(200)
        ->assertSee('Agentes')
        ->assertSee('Agent Core SRE')
        ->assertSee('agent-test-01');
});

test('admin user can view agent details with nested capabilities and metrics json without TypeError', function () {
    $agent = Agent::create([
        'agent_id' => 'b833179d-a5db-4599-8c48-83783fc9f4c5',
        'name' => 'jg_agente_local',
        'status' => 'ACTIVE',
        'version' => '0.1.0',
        'capabilities_json' => [
            'plugins_count' => 2,
            'tools' => [
                ['name' => 'restart_container', 'risk_level' => 'HIGH'],
            ],
            'engines' => [
                ['name' => 'dag_planner', 'type' => 'planner'],
            ],
        ],
        'metrics_json' => [
            'cpu_pct' => 12.5,
            'memory' => [
                'total_mb' => 8192,
                'used_mb' => 3120,
            ],
        ],
    ]);

    $response = $this->actingAs($this->adminUser)->get("/admin/agents/{$agent->agent_id}");

    $response->assertStatus(200)
        ->assertSee('jg_agente_local')
        ->assertSee('b833179d-a5db-4599-8c48-83783fc9f4c5')
        ->assertSee('restart_container')
        ->assertSee('dag_planner');
});

