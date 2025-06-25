<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminInvoicesController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Cards\CardsController;
use App\Http\Controllers\Cards\RewardProgramsController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Finance\AnalysisController;
use App\Http\Controllers\Finance\BanksController;
use App\Http\Controllers\Finance\CategoriesController;
use App\Http\Controllers\Finance\InvoicesController;
use App\Http\Controllers\Finance\SuggestionsController;
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
    Route::post('/users/{user}/change-password', [UsersController::class, 'changePassword']);
    Route::apiResource('users', UsersController::class);

    // Cartões
    Route::get('/cards/has-cards', [CardsController::class, 'hasCards']);
    Route::put('/cards/{card}/status', [CardsController::class, 'switchStatus']);
    Route::get('/cards/{card}/invoices', [CardsController::class, 'invoices']);
    Route::apiResource('cards', CardsController::class);

    // Programas de Recompensas
    Route::get('/reward-programs/{reward_program}/cards', [RewardProgramsController::class, 'cards']);
    Route::apiResource('reward-programs', RewardProgramsController::class);

    // Faturas
    Route::get('/invoices/{invoice}/suggestions', [SuggestionsController::class, 'getByInvoice']);
    Route::post('/invoices/{invoice}/suggestions', [SuggestionsController::class, 'store']);
    Route::get('/invoices/{invoice}/suggestions/stats', [SuggestionsController::class, 'getStatsByInvoice']);
    Route::post('/invoices/upload', [InvoicesController::class, 'upload']);
    Route::get('/invoices/{invoice}/transactions', [InvoicesController::class, 'getTransactions']);
    Route::get('/invoices/{invoice}/category-summary', [InvoicesController::class, 'getCategorySummary']);
    Route::apiResource('invoices', InvoicesController::class);

    // Sugestões
    Route::apiResource('suggestions', SuggestionsController::class);

    // Transações
    Route::get('/transactions/suggestions', [TransactionsController::class, 'suggestions']);
    Route::post('/transactions/{transaction}/categorize', [TransactionsController::class, 'categorize']);
    Route::apiResource('transactions', TransactionsController::class)->except(['store']);

    // Categorias
    Route::get('/categories/{category}/transactions', [CategoriesController::class, 'transactions']);
    Route::get('/categories/suggest', [CategoriesController::class, 'suggest']);
    Route::apiResource('categories', CategoriesController::class);

    // Bancos
    Route::apiResource('banks', BanksController::class);

    // Análise de dados
    Route::prefix('analysis')->group(function () {
        Route::get('/cards-recommendation', [AnalysisController::class, 'cardsRecommendation']);
        Route::get('/transaction-optimizations', [AnalysisController::class, 'transactionOptimizations']);
        Route::get('/spending-patterns', [AnalysisController::class, 'spendingPatterns']);
        Route::get('/points-summary', [AnalysisController::class, 'pointsSummary']);
    });

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'index']);
        Route::get('/stats', [DashboardController::class, 'getStats']);
        Route::get('/transactions', [DashboardController::class, 'getTransactions']);
        Route::get('/points-programs', [DashboardController::class, 'getPointsPrograms']);
        Route::get('/points-by-category', [DashboardController::class, 'getPointsByCategory']);
        Route::get('/monthly-spent', [DashboardController::class, 'getMonthlySpent']);
        Route::get('/recommendations', [DashboardController::class, 'getRecommendations']);
    });

    // Rotas administrativas (apenas para admins)
    Route::middleware(['admin'])->prefix('admin')->group(function () {

        // Dashboard e estatísticas
        Route::get('/stats', [AdminController::class, 'getStats']);
        Route::get('/activities/recent', [AdminController::class, 'getRecentActivities']);

        // Faturas
        Route::prefix('invoices')->group(function () {
            // Usuários com faturas
            Route::get('/users', [AdminInvoicesController::class, 'getUsers']);
            Route::get('/users/{userId}/invoices', [AdminInvoicesController::class, 'getUserInvoices']);

            // Detalhes de faturas
            Route::get('/pending', [AdminController::class, 'getPendingInvoices']);
            Route::get('/{invoiceId}', [AdminInvoicesController::class, 'getInvoiceDetails']);
            Route::get('/{invoiceId}/transactions', [AdminInvoicesController::class, 'getInvoiceTransactions']);
            Route::get('/{invoiceId}/category-summary', [AdminInvoicesController::class, 'getInvoiceCategorySummary']);
            
            // Ações administrativas
            Route::post('/{invoiceId}/reprocess', [AdminInvoicesController::class, 'reprocessInvoice']);
            Route::patch('/{invoiceId}/status', [AdminInvoicesController::class, 'updateInvoiceStatus']);
            Route::delete('/{invoiceId}', [AdminInvoicesController::class, 'deleteInvoice']);
           // Estatísticas
            Route::get('/stats', [AdminInvoicesController::class, 'getInvoicesStats']);
        });

        // Gestão de usuários
        Route::get('/users', [AdminController::class, 'getUsers']);
        Route::get('/users/{user}', [AdminController::class, 'getUserDetails']);
        Route::patch('/users/{user}/status', [AdminController::class, 'updateUserStatus']);

        // Gestão de faturas
        Route::get('/invoices/pending', [AdminController::class, 'getPendingInvoices']);
        Route::post('/invoices/{invoice}/reprocess', [AdminController::class, 'reprocessInvoice']);
        Route::patch('/invoices/{invoice}/priority', [AdminController::class, 'prioritizeInvoice']);

        // Sistema e saúde
        Route::get('/system/health', [AdminController::class, 'getSystemHealth']);
        Route::get('/system/logs', [AdminController::class, 'getSystemLogs']);
        Route::get('/queues/status', [AdminController::class, 'getQueueStatus']);

        // Métricas e performance
        Route::get('/metrics/performance', [AdminController::class, 'getPerformanceMetrics']);
        Route::get('/ai/usage-report', [AdminController::class, 'getAIUsageReport']);
        Route::get('/errors/stats', [AdminController::class, 'getErrorStats']);

        // Configurações do sistema
        Route::get('/settings', [AdminController::class, 'getSystemSettings']);
        Route::put('/settings', [AdminController::class, 'updateSystemSettings']);

        // Backup e manutenção
        Route::post('/backup/initiate', [AdminController::class, 'initiateBackup']);
        Route::get('/backup/status', [AdminController::class, 'getBackupStatus']);
        Route::post('/cleanup', [AdminController::class, 'cleanupOldData']);

        // Notificações administrativas
        Route::get('/notifications', [AdminController::class, 'getAdminNotifications']);
        Route::patch('/notifications/{notification}/read', [AdminController::class, 'markNotificationAsRead']);

        // Relatórios
        Route::post('/reports/generate', [AdminController::class, 'generateReport']);
        Route::get('/reports/{report}/download', [AdminController::class, 'downloadReport']);

        // Auditoria
        Route::get('/audit/logs', [AdminController::class, 'getAuditLogs']);

        // Gestão de bancos (admin)
        Route::get('/banks', [AdminController::class, 'getBanksList']);
        Route::post('/banks', [AdminController::class, 'addBank']);
        Route::put('/banks/{bank}', [AdminController::class, 'updateBank']);

        // Gestão de programas de recompensas (admin)
        Route::get('/reward-programs', [AdminController::class, 'getRewardProgramsList']);
        Route::post('/reward-programs', [AdminController::class, 'addRewardProgram']);
        Route::put('/reward-programs/{program}', [AdminController::class, 'updateRewardProgram']);
    });
});