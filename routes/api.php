<?php

use App\Http\Controllers\Api\AdminAgentController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AgentHeartbeatController;
use App\Http\Controllers\Api\AgentRegistrationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExecutionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');
    Route::post('/agents/register', [AgentRegistrationController::class, 'register'])->name('api.v1.agents.register');
    Route::post('/agents/heartbeat', [AgentHeartbeatController::class, 'heartbeat'])->name('api.v1.agents.heartbeat');

    // Protected endpoints with JWT and RBAC
    Route::middleware('jwt.auth')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

        // Agent queries and health status (accessible by all authenticated roles)
        Route::get('/agents', [AgentController::class, 'index'])->name('api.v1.agents.index');
        Route::get('/agents/{agent_id}', [AgentController::class, 'show'])->name('api.v1.agents.show');
        Route::get('/executions/{agent_id}/{thread_id}', [ExecutionController::class, 'show'])->name('api.v1.executions.show');

        // Execution triggers and HITL orchestration (accessible by operator and admin)
        Route::middleware('role:operator')->group(function () {
            Route::post('/executions/trigger', [ExecutionController::class, 'trigger'])->name('api.v1.executions.trigger');
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

Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');
Route::post('/agents/register', [AgentRegistrationController::class, 'register'])->name('api.agents.register');
Route::post('/agents/heartbeat', [AgentHeartbeatController::class, 'heartbeat'])->name('api.agents.heartbeat');
Route::middleware('jwt.auth')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::get('/agents', [AgentController::class, 'index'])->name('api.agents.index');
    Route::get('/agents/{agent_id}', [AgentController::class, 'show'])->name('api.agents.show');
    Route::get('/executions/{agent_id}/{thread_id}', [ExecutionController::class, 'show'])->name('api.executions.show');

    Route::middleware('role:operator')->group(function () {
        Route::post('/executions/trigger', [ExecutionController::class, 'trigger'])->name('api.executions.trigger');
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/agents', [AdminAgentController::class, 'index'])->name('api.admin.agents.index');
        Route::post('/agents', [AdminAgentController::class, 'store'])->name('api.admin.agents.store');
        Route::post('/agents/{agent_id}/regenerate-token', [AdminAgentController::class, 'regenerateToken'])->name('api.admin.agents.regenerate_token');
        Route::post('/agents/{agent_id}/revoke-tokens', [AdminAgentController::class, 'revokeTokens'])->name('api.admin.agents.revoke_tokens');
    });
});

