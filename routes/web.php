<?php

use App\Http\Controllers\WebConsoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/console', [WebConsoleController::class, 'index'])->name('console');
Route::get('/dashboard', [WebConsoleController::class, 'index'])->name('dashboard');
Route::post('/console/agents', [WebConsoleController::class, 'storeAgent'])->name('console.agents.store');

Route::get('/console/approvals/pending', [WebConsoleController::class, 'pendingApprovals'])->name('console.approvals.pending');
Route::post('/console/approvals/{id}/approve', [WebConsoleController::class, 'approveApproval'])->name('console.approvals.approve');
Route::post('/console/approvals/{id}/reject', [WebConsoleController::class, 'rejectApproval'])->name('console.approvals.reject');
