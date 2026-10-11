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
        if (Schema::hasTable('dashboard_approval_requests')) {
            Schema::table('dashboard_approval_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('dashboard_approval_requests', 'processing_started_at')) {
                    $table->timestamp('processing_started_at')->nullable()->index()->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('dashboard_approval_requests')) {
            Schema::table('dashboard_approval_requests', function (Blueprint $table) {
                if (Schema::hasColumn('dashboard_approval_requests', 'processing_started_at')) {
                    $table->dropColumn('processing_started_at');
                }
            });
        }
    }
};
