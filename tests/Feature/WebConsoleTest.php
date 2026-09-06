<?php

use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Operator SRE',
        'email' => 'operator-test@devops60k.local',
        'password' => Hash::make('password123'),
        'role' => 'operator',
    ]);
});

test('unauthenticated user is redirected to /admin/login when accessing /console or /dashboard', function () {
    $response = $this->get('/console');
    $response->assertRedirect('/admin/login');

    $responseDash = $this->get('/dashboard');
    $responseDash->assertRedirect('/admin/login');
});

test('GET /console returns 200 OK with modern HTML console view when authenticated', function () {
    $response = $this->actingAs($this->user)->get('/console');

    $response->assertStatus(200)
        ->assertSee('jG Control Plane', false)
        ->assertSee('id="nav-agents"', false)
        ->assertSee('id="nav-approvals"', false)
        ->assertSee('id="nav-executions"', false)
        ->assertSee('/css/console.css', false)
        ->assertSee('/js/console.js', false);
});

test('GET /dashboard alias returns 200 OK identical to /console when authenticated', function () {
    $response = $this->actingAs($this->user)->get('/dashboard');

    $response->assertStatus(200)
        ->assertSee('jG Control Plane', false)
        ->assertSee('id="view-agents"', false)
        ->assertSee('id="view-approvals"', false)
        ->assertSee('id="view-executions"', false);
});

test('web console displays preloaded database counts when authenticated', function () {
    Agent::create([
        'agent_id' => 'agent-ui-01',
        'name' => 'Agent UI Alpha',
        'status' => 'ACTIVE',
    ]);

    Agent::create([
        'agent_id' => 'agent-ui-02',
        'name' => 'Agent UI Beta',
        'status' => 'OFFLINE',
    ]);

    ApprovalRequest::create([
        'thread_id' => 'th-ui-1',
        'agent_id' => 'agent-ui-01',
        'status' => 'PENDING',
        'risk_level' => 'CRITICAL',
        'proposed_plan_json' => ['action' => 'test hitl ui'],
    ]);

    $response = $this->actingAs($this->user)->get('/console');

    $response->assertStatus(200)
        ->assertSee('id="kpi-agents-total"', false)
        ->assertSee('id="counter-hitl-pending"', false);

    $viewData = $response->viewData('totalAgents');
    expect($viewData)->toBe(2);
    expect($response->viewData('activeAgents'))->toBe(1);
    expect($response->viewData('offlineAgents'))->toBe(1);
    expect($response->viewData('pendingApprovalsCount'))->toBe(1);
});

test('root URL GET / continues to redirect to /admin preserving Filament panel path', function () {
    $response = $this->get('/');

    $response->assertRedirect('/admin');
});
