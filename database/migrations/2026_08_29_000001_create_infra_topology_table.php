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
        if (!Schema::hasTable('infra_topology')) {
            Schema::create('infra_topology', function (Blueprint $table) {
                $table->string('target', 64)->primary();
                $table->text('nodes_json');
                $table->text('edges_json');
                $table->timestamp('updated_at')->useCurrent()->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infra_topology');
    }
};
