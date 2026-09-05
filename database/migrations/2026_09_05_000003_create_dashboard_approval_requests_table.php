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
        Schema::create('dashboard_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('thread_id', 64)->index();
            $table->uuid('agent_id')->nullable()->index();
            $table->string('task_id', 64)->nullable()->index();
            $table->string('status', 32)->default('PENDING')->index();
            $table->string('risk_level', 32)->default('HIGH')->index();
            $table->text('proposed_plan_json');
            $table->text('compensation_commands_json')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('justification')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('agent_id')
                ->references('agent_id')
                ->on('dashboard_agents')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_approval_requests');
    }
};
