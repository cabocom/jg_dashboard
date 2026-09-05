<?php

use App\Http\Controllers\WebConsoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/console', [WebConsoleController::class, 'index'])->name('console');
Route::get('/dashboard', [WebConsoleController::class, 'index'])->name('dashboard');
Route::post('/console/agents', [WebConsoleController::class, 'storeAgent'])->name('console.agents.store');
