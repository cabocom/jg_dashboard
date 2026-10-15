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
use App\Models\AgentTask;
use App\Models\User;
use Tests\TestCase;

class AgentTaskResourceTest extends TestCase
{
    public function test_root_redirects_to_admin(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/admin');
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/admin/login');
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::create([
            'name' => 'SRE Admin',
            'email' => 'admin@devops60k.local',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
    }

    public function test_authenticated_user_can_access_agent_tasks(): void
    {
        $user = User::create([
            'name' => 'SRE Operator',
            'email' => 'operator@devops60k.local',
            'password' => bcrypt('password123'),
            'role' => 'operator',
        ]);

        AgentTask::create([
            'task_id' => 'task_ui_test_1',
            'thread_id' => 'thread_ui_test_1',
            'status' => 'QUEUED',
            'payload_json' => ['user_prompt' => 'Test prompt'],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/admin/agent-tasks');
        $response->assertStatus(200);
        $response->assertSee('task_ui_test_1');
    }

    public function test_unauthorized_role_user_cannot_access_dashboard(): void
    {
        $viewerUser = User::create([
            'name' => 'Guest Viewer',
            'email' => 'viewer@devops60k.local',
            'password' => bcrypt('password123'),
            'role' => 'viewer',
        ]);

        $response = $this->actingAs($viewerUser)->get('/admin');
        $response->assertStatus(403);
    }

    public function test_user_role_helper_methods(): void
    {
        $admin = new User(['role' => 'admin']);
        $operator = new User(['role' => 'operator']);
        $viewer = new User(['role' => 'viewer']);

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isOperator());

        $this->assertFalse($operator->isAdmin());
        $this->assertTrue($operator->isOperator());

        $this->assertFalse($viewer->isAdmin());
        $this->assertFalse($viewer->isOperator());
    }

    public function test_hitl_actions_visibility_based_on_task_status(): void
    {
        $operator = User::create([
            'name' => 'SRE Operator',
            'email' => 'operator_hitl@devops60k.local',
            'password' => bcrypt('password123'),
            'role' => 'operator',
        ]);

        $queuedTask = AgentTask::create([
            'task_id' => 'task_queued_test',
            'thread_id' => 'thread_queued_test',
            'status' => 'QUEUED',
            'payload_json' => ['user_prompt' => 'Queued task prompt'],
            'created_at' => now(),
        ]);

        $waitingTask = AgentTask::create([
            'task_id' => 'task_waiting_test',
            'thread_id' => 'thread_waiting_test',
            'status' => 'WAITING_APPROVAL',
            'payload_json' => ['user_prompt' => 'Waiting task prompt'],
            'created_at' => now(),
        ]);

        $this->actingAs($operator);

        \Livewire\Livewire::test(\App\Filament\Resources\AgentTaskResource\Pages\ListAgentTasks::class)
            ->assertTableActionHidden('approve_hitl', $queuedTask)
            ->assertTableActionHidden('reject_hitl', $queuedTask)
            ->assertTableActionVisible('approve_hitl', $waitingTask)
            ->assertTableActionVisible('reject_hitl', $waitingTask);
    }

    public function test_hitl_approval_action_delegates_to_agent_http_client(): void
    {
        $operator = User::create([
            'name' => 'SRE Lead Operator',
            'email' => 'operator_exec@devops60k.local',
            'password' => bcrypt('password123'),
            'role' => 'operator',
        ]);

        $agent = Agent::create([
            'agent_id' => 'agent-task-01',
            'name' => 'Agent SRE Kubernetes',
            'status' => 'ACTIVE',
            'metadata_json' => ['api_url' => 'http://test-agent:8000'],
        ]);

        $task = AgentTask::create([
            'task_id' => 'task_hitl_action_test',
            'thread_id' => 'th_hitl_action_test',
            'status' => 'WAITING_APPROVAL',
            'payload_json' => ['user_prompt' => 'Drain node-01'],
            'created_at' => now(),
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'http://test-agent:8000/executions/th_hitl_action_test/approve' => \Illuminate\Support\Facades\Http::response([
                'status' => 'resumed',
                'thread_id' => 'th_hitl_action_test',
            ], 200),
        ]);

        $this->actingAs($operator);

        \Livewire\Livewire::test(\App\Filament\Resources\AgentTaskResource\Pages\ListAgentTasks::class)
            ->callTableAction('approve_hitl', $task, data: [
                'notes' => 'Aprobado desde Filament vía AgentHttpClient',
            ])
            ->assertHasNoTableActionErrors();

        $task->refresh();
        $this->assertEquals('COMPLETED', $task->status);
    }
}

