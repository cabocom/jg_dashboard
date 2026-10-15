<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

use App\Http\Controllers\Api\Adapters\MockConversationalAdapterController;
use App\Http\Controllers\Api\Adapters\WebhookAdapterController;
use App\Http\Controllers\Api\AdminAgentController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AgentHeartbeatController;
use App\Http\Controllers\Api\AgentRegistrationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExecutionController;
use App\Http\Controllers\Api\HitlApprovalController;
use App\Http\Controllers\Api\InternalExecutionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');
    Route::post('/agents/register', [AgentRegistrationController::class, 'register'])->name('api.v1.agents.register');
    Route::post('/agents/heartbeat', [AgentHeartbeatController::class, 'heartbeat'])->middleware('agent.hmac')->name('api.v1.agents.heartbeat');
    Route::post('/internal/executions/{execution_id}/status-update', [InternalExecutionController::class, 'statusUpdate'])->middleware('agent.hmac')->name('api.v1.internal.executions.status_update');
    Route::post('/internal/executions/{execution_id}/complete', [InternalExecutionController::class, 'complete'])->name('api.v1.internal.executions.complete');

    // External Stimuli Adapters Gateway (Fase 08: ISSUE-F08-T02)
    Route::post('/adapters/webhook', [WebhookAdapterController::class, 'handle'])->name('api.v1.adapters.webhook');
    Route::post('/adapters/webhook/{agent_id}', [WebhookAdapterController::class, 'handle'])->name('api.v1.adapters.webhook.agent');

    // Simulated Conversational / Asterisk Gateway (Fase 08: ISSUE-F08-T03)
    Route::post('/adapters/mock-conversational', [MockConversationalAdapterController::class, 'handle'])->name('api.v1.adapters.mock_conversational');
    Route::post('/adapters/mock-conversational/{agent_id}', [MockConversationalAdapterController::class, 'handle'])->name('api.v1.adapters.mock_conversational.agent');
    Route::post('/adapters/mock-asterisk', [MockConversationalAdapterController::class, 'handle'])->name('api.v1.adapters.mock_asterisk');
    Route::post('/adapters/mock-asterisk/{agent_id}', [MockConversationalAdapterController::class, 'handle'])->name('api.v1.adapters.mock_asterisk.agent');

    // Protected endpoints with JWT and RBAC
    Route::middleware('jwt.auth')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

        // Agent queries and health status (accessible by all authenticated roles)
        Route::get('/agents', [AgentController::class, 'index'])->name('api.v1.agents.index');
        Route::get('/agents/{agent_id}', [AgentController::class, 'show'])->name('api.v1.agents.show');
        Route::get('/agents/{agent_id}/capabilities', [AgentController::class, 'capabilities'])->name('api.v1.agents.capabilities');
        Route::get('/executions/{agent_id}/{thread_id}', [ExecutionController::class, 'show'])->name('api.v1.executions.show');

        // Execution triggers and HITL orchestration (accessible by operator and admin)
        Route::middleware('role:operator')->group(function () {
            Route::post('/executions/trigger', [ExecutionController::class, 'trigger'])->name('api.v1.executions.trigger');
            Route::get('/approvals/pending', [HitlApprovalController::class, 'pending'])->name('api.v1.approvals.pending');
            Route::post('/approvals/{approval_id}/approve', [HitlApprovalController::class, 'approve'])->name('api.v1.approvals.approve');
            Route::post('/approvals/{approval_id}/reject', [HitlApprovalController::class, 'reject'])->name('api.v1.approvals.reject');
        });

        // Admin endpoints
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('/agents', [AdminAgentController::class, 'index'])->name('api.v1.admin.agents.index');
            Route::post('/agents', [AdminAgentController::class, 'store'])->name('api.v1.admin.agents.store');
            Route::post('/agents/{agent_id}/regenerate-token', [AdminAgentController::class, 'regenerateToken'])->name('api.v1.admin.agents.regenerate_token');
            Route::post('/agents/{agent_id}/revoke-tokens', [AdminAgentController::class, 'revokeTokens'])->name('api.v1.admin.agents.revoke_tokens');
        });

        Route::middleware('role:admin')->get('/test/admin-only', function () {
            return response()->json(['status' => 'ok', 'message' => 'Admin resource accessed']);
        });

        Route::middleware('role:operator')->get('/test/operator-only', function () {
            return response()->json(['status' => 'ok', 'message' => 'Operator resource accessed']);
        });

        Route::middleware('role:viewer')->get('/test/viewer-only', function () {
            return response()->json(['status' => 'ok', 'message' => 'Viewer resource accessed']);
        });

        Route::middleware('permission:agents:manage')->get('/test/permission-manage', function () {
            return response()->json(['status' => 'ok', 'message' => 'Agent manage permission granted']);
        });
    });
});

