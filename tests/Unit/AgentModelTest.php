<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace Tests\Unit;

use App\Models\Agent;
use Tests\TestCase;

class AgentModelTest extends TestCase
{
    public function test_agent_creation_and_uuid_generation(): void
    {
        $agent = Agent::create([
            'name' => 'Agent-DevOps-Cluster-A',
            'version' => '0.1.0',
            'capabilities_json' => ['tools' => ['docker_ps', 'docker_restart']],
            'metadata_json' => ['region' => 'eu-west-1'],
        ]);

        $this->assertNotNull($agent->agent_id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $agent->agent_id);
        $this->assertEquals('PENDING', $agent->status);
        $this->assertTrue($agent->isPending());
        $this->assertFalse($agent->isActive());
        $this->assertFalse($agent->isOffline());

        $this->assertIsArray($agent->capabilities_json);
        $this->assertContains('docker_ps', $agent->capabilities_json['tools']);
        $this->assertIsArray($agent->metadata_json);
        $this->assertEquals('eu-west-1', $agent->metadata_json['region']);
    }

    public function test_agent_hidden_attributes_in_serialization(): void
    {
        $agent = Agent::create([
            'name' => 'Agent-Secret-Test',
            'permanent_secret_hash' => password_hash('super_secret_token', PASSWORD_BCRYPT),
        ]);

        $array = $agent->toArray();
        $this->assertArrayNotHasKey('permanent_secret_hash', $array);
    }

    public function test_agent_scopes(): void
    {
        Agent::create(['name' => 'Agent-Pending', 'status' => 'PENDING']);
        Agent::create(['name' => 'Agent-Active', 'status' => 'ACTIVE']);
        Agent::create(['name' => 'Agent-Offline', 'status' => 'OFFLINE']);

        $this->assertEquals(1, Agent::pending()->count());
        $this->assertEquals(1, Agent::active()->count());
        $this->assertEquals(1, Agent::offline()->count());
    }

    public function test_agent_health_check_recency(): void
    {
        $healthyAgent = Agent::create([
            'name' => 'Agent-Healthy',
            'status' => 'ACTIVE',
            'last_heartbeat_at' => now()->subSeconds(30),
        ]);

        $staleAgent = Agent::create([
            'name' => 'Agent-Stale',
            'status' => 'ACTIVE',
            'last_heartbeat_at' => now()->subSeconds(120),
        ]);

        $pendingAgent = Agent::create([
            'name' => 'Agent-NoHeartbeat',
            'status' => 'PENDING',
            'last_heartbeat_at' => null,
        ]);

        $this->assertTrue($healthyAgent->isHealthy(90));
        $this->assertFalse($staleAgent->isHealthy(90));
        $this->assertFalse($pendingAgent->isHealthy(90));
    }

    public function test_agent_state_transitions(): void
    {
        $agent = Agent::create([
            'name' => 'Agent-Transition-Test',
            'status' => 'PENDING',
        ]);

        $secretHash = password_hash('permanent_secret_123', PASSWORD_BCRYPT);
        $agent->markAsActive($secretHash);

        $this->assertEquals('ACTIVE', $agent->fresh()->status);
        $this->assertEquals($secretHash, $agent->fresh()->permanent_secret_hash);
        $this->assertNotNull($agent->fresh()->last_heartbeat_at);
        $this->assertTrue($agent->fresh()->isActive());

        // Heartbeat recording
        $agent->recordHeartbeat(['cpu_percent' => 15.5, 'memory_mb' => 256]);
        $refreshed = $agent->fresh();
        $this->assertIsArray($refreshed->metrics_json);
        $this->assertEquals(15.5, $refreshed->metrics_json['cpu_percent']);

        // Mark offline
        $agent->markAsOffline();
        $this->assertEquals('OFFLINE', $agent->fresh()->status);
        $this->assertTrue($agent->fresh()->isOffline());
    }
}
