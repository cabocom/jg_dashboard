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
        Schema::create('dashboard_bootstrap_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->uuid('agent_id')->index();
            $table->timestamp('expires_at')->index();
            $table->boolean('consumed')->default(false)->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->foreign('agent_id')
                ->references('agent_id')
                ->on('dashboard_agents')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_bootstrap_tokens');
    }
};
