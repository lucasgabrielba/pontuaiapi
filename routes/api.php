<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Cards\CardsController;
use App\Http\Controllers\Cards\RewardProgramsController;
use App\Http\Controllers\Finance\AnalysisController;
use App\Http\Controllers\Finance\CategoriesController;
use App\Http\Controllers\Finance\InvoicesController;
use App\Http\Controllers\Finance\TransactionsController;
use App\Http\Controllers\Users\UsersController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show'])
    ->middleware('web');

// Rotas públicas de autenticação
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Rotas protegidas por autenticação
Route::middleware('auth:sanctum')->group(function () {

    // Autenticação
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::get('/check', [AuthController::class, 'check']);
    });

    // Usuários
    Route::apiResource('users', UsersController::class);
    Route::post('/users/{user}/change-password', [UsersController::class, 'changePassword']);

    // Cartões
    Route::apiResource('cards', CardsController::class);
    Route::get('/cards/{card}/invoices', [CardsController::class, 'invoices']);

    // Programas de Recompensas
    Route::apiResource('reward-programs', RewardProgramsController::class);
    Route::get('/reward-programs/{reward_program}/cards', [RewardProgramsController::class, 'cards']);

    // Faturas
    Route::apiResource('invoices', InvoicesController::class);
    Route::post('/invoices/upload', [InvoicesController::class, 'upload']);
    Route::get('/invoices/{invoice}/transactions', [InvoicesController::class, 'transactions']);

    // Transações
    Route::apiResource('transactions', TransactionsController::class)->except(['store']);
    Route::get('/transactions/suggestions', [TransactionsController::class, 'suggestions']);
    Route::post('/transactions/{transaction}/categorize', [TransactionsController::class, 'categorize']);

    // Categorias
    Route::apiResource('categories', CategoriesController::class);
    Route::get('/categories/{category}/transactions', [CategoriesController::class, 'transactions']);
    Route::get('/categories/suggest', [CategoriesController::class, 'suggest']);

    // Análise de dados
    Route::prefix('analysis')->group(function () {
        Route::get('/cards-recommendation', [AnalysisController::class, 'cardsRecommendation']);
        Route::get('/spending-patterns', [AnalysisController::class, 'spendingPatterns']);
        Route::get('/points-summary', [AnalysisController::class, 'pointsSummary']);
    });
});