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

use App\Models\AgentTask;
use App\Models\CircuitBreakerState;
use App\Models\NotifiedIncident;
use Tests\TestCase;

class ModelMappingTest extends TestCase
{
    public function test_agent_task_casts_and_primary_key(): void
    {
        $task = AgentTask::create([
            'task_id' => 'task_test_123',
            'thread_id' => 'thread_abc_456',
            'status' => 'QUEUED',
            'payload_json' => ['user_prompt' => 'Check cluster status', 'user_id' => 'usr_01'],
            'created_at' => now(),
            'trace_history_json' => [
                ['timestamp' => '2026-08-29T00:00:00Z', 'event' => 'HTTP_REQUEST_RECEIVED', 'detail' => 'Init'],
            ],
        ]);

        $this->assertFalse($task->incrementing);
        $this->assertEquals('string', $task->getKeyType());
        $this->assertEquals('task_test_123', $task->getKey());

        $retrieved = AgentTask::find('task_test_123');
        $this->assertNotNull($retrieved);
        $this->assertIsArray($retrieved->payload_json);
        $this->assertEquals('Check cluster status', $retrieved->payload_json['user_prompt']);
        $this->assertIsArray($retrieved->trace_history_json);
        $this->assertCount(1, $retrieved->trace_history_json);
    }

    public function test_agent_task_scopes_and_duration(): void
    {
        AgentTask::create([
            'task_id' => 'task_pending_1',
            'thread_id' => 'thread_1',
            'status' => 'QUEUED',
            'payload_json' => [],
            'created_at' => now(),
        ]);

        AgentTask::create([
            'task_id' => 'task_failed_1',
            'thread_id' => 'thread_2',
            'status' => 'FAILED',
            'payload_json' => [],
            'created_at' => now(),
        ]);

        AgentTask::create([
            'task_id' => 'task_completed_1',
            'thread_id' => 'thread_3',
            'status' => 'COMPLETED',
            'payload_json' => [],
            'created_at' => now()->subSeconds(10),
            'started_at' => now()->subSeconds(10),
            'completed_at' => now(),
        ]);

        $this->assertEquals(1, AgentTask::pending()->count());
        $this->assertEquals(1, AgentTask::failed()->count());
        $this->assertEquals(1, AgentTask::completed()->count());

        $completedTask = AgentTask::find('task_completed_1');
        $this->assertNotNull($completedTask->duration);
    }

    public function test_circuit_breaker_state_casts_and_scopes(): void
    {
        CircuitBreakerState::create([
            'target' => 'k8s_api',
            'is_tripped' => 1,
            'tripped_reason' => 'Too many 503 errors',
            'failure_history_json' => ['failures' => 5],
            'updated_at' => now(),
        ]);

        CircuitBreakerState::create([
            'target' => 'docker_daemon',
            'is_tripped' => 0,
            'tripped_reason' => null,
            'failure_history_json' => [],
            'updated_at' => now(),
        ]);

        $tripped = CircuitBreakerState::find('k8s_api');
        $this->assertTrue($tripped->is_tripped);
        $this->assertIsArray($tripped->failure_history_json);
        $this->assertEquals(5, $tripped->failure_history_json['failures']);

        $this->assertEquals(1, CircuitBreakerState::tripped()->count());
        $this->assertEquals(1, CircuitBreakerState::healthy()->count());
    }

    public function test_notified_incident_mapping(): void
    {
        $incident = NotifiedIncident::create([
            'incident_id' => 'inc_pod_crash_789',
            'notified_at' => now(),
        ]);

        $this->assertFalse($incident->incrementing);
        $this->assertEquals('inc_pod_crash_789', $incident->getKey());
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $incident->notified_at);
    }

    public function test_infra_topology_model_mapping_and_counts(): void
    {
        $topology = \App\Models\InfraTopology::create([
            'target' => 'current',
            'nodes_json' => [
                ['id' => 'host', 'label' => 'Host System', 'type' => 'host', 'status' => 'healthy'],
                ['id' => 'agent', 'label' => 'Agent', 'type' => 'container', 'status' => 'healthy'],
                ['id' => 'db', 'label' => 'DB', 'type' => 'database', 'status' => 'down'],
                ['id' => 'proxy', 'label' => 'Proxy', 'type' => 'container', 'status' => 'warning'],
            ],
            'edges_json' => [
                ['from' => 'host', 'to' => 'agent', 'relation' => 'contains'],
                ['from' => 'agent', 'to' => 'db', 'relation' => 'database_connection'],
            ],
            'updated_at' => now(),
        ]);

        $this->assertFalse($topology->incrementing);
        $this->assertEquals('string', $topology->getKeyType());
        $this->assertEquals('current', $topology->getKey());
        $this->assertEquals(4, $topology->nodes_count);
        $this->assertEquals(2, $topology->edges_count);
        $this->assertEquals(2, $topology->healthy_count);
        $this->assertEquals(1, $topology->down_count);
        $this->assertEquals(1, $topology->warning_count);
    }
}
