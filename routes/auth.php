<?php

use App\Http\Controllers\Auth\AuthController;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('check', [AuthController::class, 'check']);
    Route::get('get-me', [AuthController::class, 'user']);
    Route::post('logout', [AuthController::class, 'logout']);
});
