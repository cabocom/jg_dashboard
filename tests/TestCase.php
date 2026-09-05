<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabaseSchema();
    }

    /**
     * Create in-memory tables replicating DevOps60K schema for testing.
     */
    protected function setUpDatabaseSchema(): void
    {
        if (!Schema::hasTable('agent_tasks')) {
            Schema::create('agent_tasks', function (Blueprint $table) {
                $table->string('task_id', 64)->primary();
                $table->string('thread_id', 64);
                $table->string('status', 32);
                $table->text('payload_json');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('result_json')->nullable();
                $table->text('trace_history_json')->default('[]');
            });
        }

        if (!Schema::hasTable('circuit_breaker_state')) {
            Schema::create('circuit_breaker_state', function (Blueprint $table) {
                $table->string('target', 128)->primary();
                $table->integer('is_tripped')->default(0);
                $table->text('tripped_reason')->nullable();
                $table->text('failure_history_json')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('notified_incidents')) {
            Schema::create('notified_incidents', function (Blueprint $table) {
                $table->string('incident_id', 128)->primary();
                $table->timestamp('notified_at')->nullable();
            });
        }

        if (!Schema::hasTable('infra_topology')) {
            Schema::create('infra_topology', function (Blueprint $table) {
                $table->string('target', 64)->primary();
                $table->text('nodes_json');
                $table->text('edges_json');
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('role')->default('operator');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('dashboard_agents')) {
            Schema::create('dashboard_agents', function (Blueprint $table) {
                $table->uuid('agent_id')->primary();
                $table->string('name');
                $table->string('version')->nullable();
                $table->string('status')->default('PENDING')->index();
                $table->string('permanent_secret_hash')->nullable();
                $table->timestamp('last_heartbeat_at')->nullable()->index();
                $table->text('capabilities_json')->nullable();
                $table->text('metrics_json')->nullable();
                $table->text('metadata_json')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('dashboard_bootstrap_tokens')) {
            Schema::create('dashboard_bootstrap_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('token_hash', 64)->unique();
                $table->uuid('agent_id')->index();
                $table->timestamp('expires_at')->index();
                $table->boolean('consumed')->default(false)->index();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();
            });
        }
    }
}
