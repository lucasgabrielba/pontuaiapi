<?php

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

Route::prefix('auth')->group(base_path('routes/auth.php'));

Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show'])
    ->middleware('web');

Route::middleware('auth:sanctum')
    ->group(function () {
        
        //Users Domains
        require base_path('routes/Users/users.php');
    });

