<?php

namespace Tests\Feature;

use App\Services\DevOps60kApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DevOps60kApiClientTest extends TestCase
{
    public function test_check_health_successful(): void
    {
        Http::fake([
            '*/health' => Http::response([
                'status' => 'healthy',
                'api' => 'operational',
                'worker' => 'idle',
            ], 200),
        ]);

        /** @var DevOps60kApiClient $client */
        $client = app(DevOps60kApiClient::class);
        $result = $client->checkHealth();

        $this->assertTrue($result['online']);
        $this->assertEquals('healthy', $result['status']);
    }

    public function test_check_health_unreachable(): void
    {
        Http::fake([
            '*/health' => Http::response(null, 503),
        ]);

        /** @var DevOps60kApiClient $client */
        $client = app(DevOps60kApiClient::class);
        $result = $client->checkHealth();

        $this->assertFalse($result['online']);
        $this->assertContains($result['status'], ['http_error', 'unreachable']);
    }

    public function test_trigger_task_sends_correct_payload_and_headers(): void
    {
        Http::fake([
            '*/trigger' => Http::response([
                'task_id' => 'task_111',
                'thread_id' => 'thread_222',
                'status' => 'QUEUED',
            ], 200),
        ]);

        /** @var DevOps60kApiClient $client */
        $client = app(DevOps60kApiClient::class);
        $result = $client->triggerTask('Verify Kubernetes cluster', 'usr_admin', 'sysadmin');

        $this->assertEquals('task_111', $result['task_id']);
        $this->assertEquals('QUEUED', $result['status']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/trigger') &&
                   $request['user_prompt'] === 'Verify Kubernetes cluster' &&
                   $request['user_id'] === 'usr_admin' &&
                   $request['username'] === 'sysadmin';
        });
    }

    public function test_approve_execution_sends_request_to_endpoint(): void
    {
        Http::fake([
            '*/executions/sess_test_123/approve' => Http::response([
                'status' => 'approved',
                'thread_id' => 'sess_test_123',
            ], 200),
        ]);

        /** @var DevOps60kApiClient $client */
        $client = app(DevOps60kApiClient::class);
        $result = $client->approveExecution('sess_test_123', 'Verified and approved by SRE lead');

        $this->assertEquals('approved', $result['status']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/executions/sess_test_123/approve') &&
                   $request['notes'] === 'Verified and approved by SRE lead';
        });
    }

    public function test_reject_execution_sends_request_to_endpoint(): void
    {
        Http::fake([
            '*/executions/sess_test_456/reject' => Http::response([
                'status' => 'rejected',
                'thread_id' => 'sess_test_456',
            ], 200),
        ]);

        /** @var DevOps60kApiClient $client */
        $client = app(DevOps60kApiClient::class);
        $result = $client->rejectExecution('sess_test_456', 'Too risky to perform at peak hours');

        $this->assertEquals('rejected', $result['status']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/executions/sess_test_456/reject') &&
                   $request['reason'] === 'Too risky to perform at peak hours';
        });
    }
}
