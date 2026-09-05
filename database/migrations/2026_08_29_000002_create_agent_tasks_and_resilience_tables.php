<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
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
                $table->boolean('is_tripped')->default(false);
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notified_incidents');
        Schema::dropIfExists('circuit_breaker_state');
        Schema::dropIfExists('agent_tasks');
    }
};
