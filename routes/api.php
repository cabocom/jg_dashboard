<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');
});

Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');
