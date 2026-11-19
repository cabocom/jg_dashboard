<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

use App\Http\Controllers\WebConsoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

Route::middleware(['auth'])->group(function () {
    Route::get('/console', [WebConsoleController::class, 'index'])->name('console');
    Route::get('/dashboard', [WebConsoleController::class, 'index'])->name('dashboard');
    Route::get('/console/agents', [WebConsoleController::class, 'listAgents'])->name('console.agents.index');
    Route::get('/console/agents/{agent_id}/capabilities', [WebConsoleController::class, 'agentCapabilities'])->name('console.agents.capabilities');
    Route::get('/console/approvals/pending', [WebConsoleController::class, 'pendingApprovals'])->name('console.approvals.pending');
    Route::get('/console/executions', [WebConsoleController::class, 'listExecutions'])->name('console.executions.index');
    Route::get('/console/executions/{agent_id}/{thread_id}', [WebConsoleController::class, 'showExecution'])->name('console.executions.show');
    Route::get('/console/profile', [\App\Http\Controllers\ConsoleProfileController::class, 'getProfile'])->name('console.profile.get');
    Route::match(['post', 'put'], '/console/profile', [\App\Http\Controllers\ConsoleProfileController::class, 'updateProfile'])->name('console.profile.update');

    // Agent provisioning: admin only
    Route::middleware(['role:admin'])->group(function () {
        Route::post('/console/agents', [WebConsoleController::class, 'storeAgent'])->name('console.agents.store');
    });

    // HITL approvals & execution triggers: admin and operator
    Route::middleware(['role:admin,operator'])->group(function () {
        Route::post('/console/approvals/{id}/approve', [WebConsoleController::class, 'approveApproval'])->name('console.approvals.approve');
        Route::post('/console/approvals/{id}/reject', [WebConsoleController::class, 'rejectApproval'])->name('console.approvals.reject');
        Route::post('/console/executions/trigger', [WebConsoleController::class, 'triggerExecution'])->name('console.executions.trigger');
    });
});
