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
        Schema::create('dashboard_agents', function (Blueprint $table) {
            $table->uuid('agent_id')->primary();
            $table->string('name');
            $table->string('version')->nullable();
            $table->string('status')->default('PENDING')->index(); // PENDING, ACTIVE, OFFLINE
            $table->string('permanent_secret_hash')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable()->index();
            $table->json('capabilities_json')->nullable();
            $table->json('metrics_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_agents');
    }
};
