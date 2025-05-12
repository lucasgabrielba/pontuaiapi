<?php

use App\Http\Controllers\Users\UsersController;

Route::apiResource('users', UsersController::class);

Route::post('users/{user}/change-password', [UsersController::class, 'changePassword']);