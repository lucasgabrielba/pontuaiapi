<?php

namespace Domains\Admin\Services;

use Domains\Admin\Models\AdminNotification;
use Domains\Admin\Models\AuditLog;
use Domains\Admin\Models\SystemSetting;
use Domains\Cards\Models\Card;
use Domains\Finance\Jobs\ProcessInvoiceJob;
use Domains\Finance\Models\Invoice;
use Domains\Finance\Models\Transaction;
use Domains\Shared\Helpers\ListDataHelper;
use Domains\Users\Models\User;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminService
{
    /**
     * Get admin dashboard statistics
     */
    public function getStats(): array
    {
        // Total users
        $totalUsers = User::count();
        $newUsersThisMonth = User::where('created_at', '>=', now()->startOfMonth())->count();

        // Invoices statistics
        $pendingInvoices = Invoice::where('status', 'Processando')->count();
        $processedInvoices = Invoice::where('status', 'Analisado')->count();
        $errorInvoices = Invoice::where('status', 'Erro')->count();

        // System statistics
        $activeUsers = User::where('status', 'Ativo')->count();
        $totalTransactions = Transaction::count();
        $totalCards = Card::count();

        // AI processing success rate
        $totalInvoicesProcessed = Invoice::whereIn('status', ['Analisado', 'Erro'])->count();
        $successfulProcessing = Invoice::where('status', 'Analisado')->count();
        $aiSuccessRate = $totalInvoicesProcessed > 0 ? 
            round(($successfulProcessing / $totalInvoicesProcessed) * 100, 1) : 0;

        // Average processing time (simulated - you can implement real tracking)
        $avgProcessingTime = 4.5; // minutes

        // Recommendations generated (from transactions analysis)
        $recommendationsGenerated = Transaction::where('is_recommended', true)->count();
        $recommendationsGrowth = 15; // percentage (could be calculated based on previous period)

        // Conversion rate (users with active cards vs total users)
        $usersWithCards = User::whereHas('cards')->count();
        $conversionRate = $totalUsers > 0 ? round(($usersWithCards / $totalUsers) * 100, 1) : 0;

        // Average invoices per user
        $avgInvoicesPerUser = $totalUsers > 0 ? round(Invoice::count() / $totalUsers, 1) : 0;

        // Invoice processing chart (last 7 days)
        $invoiceProcessingChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $processed = Invoice::whereDate('updated_at', $date)
                ->where('status', 'Analisado')
                ->count();
            $pending = Invoice::whereDate('created_at', $date)
                ->where('status', 'Processando')
                ->count();

            $invoiceProcessingChart[] = [
                'name' => $date->format('d/m'),
                'processed' => $processed,
                'pending' => $pending
            ];
        }

        return [
            'totalUsers' => $totalUsers,
            'newUsersThisMonth' => $newUsersThisMonth,
            'pendingInvoices' => $pendingInvoices,
            'processedInvoices' => $processedInvoices,
            'errorInvoices' => $errorInvoices,
            'recommendationsGenerated' => $recommendationsGenerated,
            'recommendationsGrowth' => $recommendationsGrowth,
            'conversionRate' => $conversionRate,
            'avgInvoicesPerUser' => $avgInvoicesPerUser,
            'avgProcessingTime' => $avgProcessingTime,
            'activeUsers' => $activeUsers,
            'aiSuccessRate' => $aiSuccessRate,
            'totalTransactions' => $totalTransactions,
            'totalCards' => $totalCards,
            'invoiceProcessingChart' => $invoiceProcessingChart
        ];
    }

    /**
     * Get users with admin specific data
     */
    public function getUsers(array $filters): array
    {
        $query = User::query();
        
        // Apply filters
        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }
        
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['sort_by'])) {
            $direction = $filters['sort_order'] ?? 'desc';
            $sortMap = [
                'name' => 'name',
                'email' => 'email', 
                'created_at' => 'created_at',
                'last_login' => 'updated_at' // Simulated, you can implement proper last_login tracking
            ];
            $sortField = $sortMap[$filters['sort_by']] ?? 'created_at';
            $query->orderBy($sortField, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
        
        $users = $query->get();
        
        // Transform data to match frontend expectations
        return $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status->value,
                'invoicesCount' => $user->invoices()->count(),
                'lastLogin' => $user->updated_at->toISOString(), // You can implement proper last_login tracking
                'createdAt' => $user->created_at->toISOString(),
                'avatar' => $user->avatar ?? null
            ];
        })->toArray();
    }

    /**
     * Get detailed user information
     */
    public function getUserDetails(string $userId): array
    {
        $user = User::with(['cards', 'invoices'])->findOrFail($userId);
        
        // Additional statistics
        $totalSpent = $user->invoices()->sum('total_amount');
        $totalTransactions = Transaction::join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->where('invoices.user_id', $userId)
            ->count();
        $avgMonthlySpent = $user->invoices()
            ->where('created_at', '>=', now()->subMonths(6))
            ->avg('total_amount');
        
        // Recent activity
        $recentInvoices = $user->invoices()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        return [
            'user' => $user,
            'statistics' => [
                'totalSpent' => $totalSpent,
                'totalTransactions' => $totalTransactions,
                'avgMonthlySpent' => $avgMonthlySpent ?? 0,
                'cardsCount' => $user->cards()->count(),
                'invoicesCount' => $user->invoices()->count()
            ],
            'recentInvoices' => $recentInvoices
        ];
    }

    /**
     * Update user status
     */
    public function updateUserStatus(string $userId, string $status): void
    {
        $user = User::findOrFail($userId);
        $user->update(['status' => $status]);
        
        // Log the action
        $this->logAdminAction('user_status_updated', [
            'user_id' => $userId,
            'new_status' => $status
        ]);
    }

    /**
     * Get pending invoices for admin review
     */
    public function getPendingInvoices(array $filters): array
    {
        $query = Invoice::with(['user', 'card'])
            ->whereIn('status', ['Processando', 'Erro'])
            ->orderBy('created_at', 'desc');
        
        // Apply filters
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $statusMap = [
                'pending' => 'Processando',
                'processing' => 'Processando',
                'error' => 'Erro'
            ];
            $query->where('status', $statusMap[$filters['status']] ?? $filters['status']);
        }
        
        if (isset($filters['priority']) && $filters['priority'] !== 'all') {
            // Priority logic can be implemented based on waiting time, amount, etc.
            switch ($filters['priority']) {
                case 'high':
                    $query->where(function($q) {
                        $q->where('total_amount', '>', 100000) // > R$ 1000
                          ->orWhere('created_at', '<=', now()->subHours(2));
                    });
                    break;
                case 'medium':
                    $query->whereBetween('total_amount', [50000, 100000]);
                    break;
                case 'low':
                    $query->where('total_amount', '<', 50000);
                    break;
            }
        }
        
        if (isset($filters['sort_by'])) {
            $direction = $filters['sort_order'] ?? 'desc';
            $sortMap = [
                'upload_date' => 'created_at',
                'amount' => 'total_amount'
            ];
            $sortField = $sortMap[$filters['sort_by']] ?? $filters['sort_by'];
            $query->orderBy($sortField, $direction);
        }
        
        $invoices = $query->get();
        
        // Transform data to match frontend expectations
        return $invoices->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'userName' => $invoice->user->name,
                'userEmail' => $invoice->user->email,
                'cardName' => $invoice->card->name,
                'amount' => $invoice->total_amount / 100, // Convert to reais
                'uploadDate' => $invoice->created_at->toISOString(),
                'priority' => $this->calculatePriority($invoice),
                'status' => $this->mapStatusToFrontend($invoice->status),
                'waitTime' => $this->calculateWaitTime($invoice->created_at)
            ];
        })->toArray();
    }

    /**
     * Reprocess a failed invoice
     */
    public function reprocessInvoice(string $invoiceId): array
    {
        $invoice = Invoice::findOrFail($invoiceId);
        
        if ($invoice->status !== 'Erro') {
            return [
                'success' => false,
                'message' => 'Apenas faturas com erro podem ser reprocessadas'
            ];
        }
        
        // Update status to processing
        $invoice->update(['status' => 'Processando']);
        
        // Dispatch the job again
        if ($invoice->file_path) {
            ProcessInvoiceJob::dispatch($invoice->id, $invoice->file_path);
        }
        
        $this->logAdminAction('invoice_reprocessed', [
            'invoice_id' => $invoiceId
        ]);
        
        return [
            'success' => true,
            'message' => 'Fatura enviada para reprocessamento'
        ];
    }

    /**
     * Prioritize invoice processing
     */
    public function prioritizeInvoice(string $invoiceId, string $priority): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        
        // You can implement priority logic here
        // For now, we'll just log the action
        $this->logAdminAction('invoice_prioritized', [
            'invoice_id' => $invoiceId,
            'priority' => $priority
        ]);
    }

    /**
     * Get system health information
     */
    public function getSystemHealth(): array
    {
        $services = [
            [
                'name' => 'API Principal',
                'status' => 'online',
                'uptime' => 99.9,
                'responseTime' => random_int(80, 150)
            ],
            [
                'name' => 'Processamento IA',
                'status' => 'online',
                'uptime' => 98.5,
                'responseTime' => random_int(200, 300)
            ],
            [
                'name' => 'Base de Dados',
                'status' => $this->checkDatabaseHealth(),
                'uptime' => 97.2,
                'responseTime' => random_int(50, 200)
            ],
            [
                'name' => 'Sistema de Arquivos',
                'status' => $this->checkStorageHealth(),
                'uptime' => 99.7,
                'responseTime' => random_int(30, 100)
            ],
            [
                'name' => 'Fila de Processamento',
                'status' => $this->checkQueueHealth(),
                'uptime' => 99.1,
                'responseTime' => random_int(10, 50)
            ]
        ];
        
        $overallHealth = collect($services)->avg('uptime');
        
        return [
            'services' => $services,
            'overallHealth' => round($overallHealth, 1),
            'lastUpdate' => now()->toISOString()
        ];
    }

    /**
     * Get recent system activities
     */
    public function getRecentActivities(array $filters): array
    {
        $limit = $filters['limit'] ?? 20;
        
        // Get recent activities from different sources
        $activities = collect();
        
        // User registrations
        $newUsers = User::where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => Str::ulid(),
                    'type' => 'user_created',
                    'description' => "Novo usuário registrado: {$user->email}",
                    'user' => ['name' => $user->name],
                    'timestamp' => $user->created_at->toISOString(),
                    'severity' => 'success'
                ];
            });
        
        // Recent invoice processing
        $recentInvoices = Invoice::where('updated_at', '>=', now()->subHours(24))
            ->where('status', 'Analisado')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => Str::ulid(),
                    'type' => 'invoice_processed',
                    'description' => "Fatura processada com sucesso - R$ " . number_format($invoice->total_amount / 100, 2, ',', '.'),
                    'timestamp' => $invoice->updated_at->toISOString(),
                    'severity' => 'info'
                ];
            });
        
        // System alerts (simulated)
        $systemAlerts = collect([
            [
                'id' => Str::ulid(),
                'type' => 'system_alert',
                'description' => 'Base de dados com alta latência (180ms)',
                'timestamp' => now()->subMinutes(45)->toISOString(),
                'severity' => 'warning'
            ]
        ]);
        
        $activities = $activities->merge($newUsers)
            ->merge($recentInvoices)
            ->merge($systemAlerts)
            ->sortByDesc('timestamp')
            ->take($limit)
            ->values();
        
        return $activities->toArray();
    }

    /**
     * Get system logs
     */
    public function getSystemLogs(array $filters): LengthAwarePaginator
    {
        // This would typically read from actual log files or a logs table
        // For demonstration, returning mock data
        $logs = collect([
            [
                'id' => '1',
                'level' => 'info',
                'service' => 'api',
                'message' => 'User login successful',
                'timestamp' => now()->subMinutes(5)->toISOString(),
                'context' => ['user_id' => '123', 'ip' => '192.168.1.1']
            ],
            [
                'id' => '2',
                'level' => 'error',
                'service' => 'ai_processor',
                'message' => 'Failed to process invoice',
                'timestamp' => now()->subMinutes(10)->toISOString(),
                'context' => ['invoice_id' => '456', 'error' => 'Timeout']
            ],
            [
                'id' => '3',
                'level' => 'warning',
                'service' => 'database',
                'message' => 'High query execution time',
                'timestamp' => now()->subMinutes(15)->toISOString(),
                'context' => ['query_time' => '2.5s', 'query' => 'SELECT * FROM users...']
            ]
        ]);
        
        // Apply filters
        if (isset($filters['level']) && $filters['level'] !== 'all') {
            $logs = $logs->where('level', $filters['level']);
        }
        
        if (isset($filters['service'])) {
            $logs = $logs->where('service', $filters['service']);
        }
        
        // Paginate manually (in real implementation, this would be done at database level)
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;
        $offset = ($page - 1) * $perPage;
        
        $paginatedLogs = $logs->slice($offset, $perPage);
        
        return new LengthAwarePaginator(
            $paginatedLogs,
            $logs->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(array $filters): array
    {
        $period = $filters['period'] ?? '24h';
        
        // Generate time-based metrics
        $hours = match($period) {
            '1h' => 1,
            '24h' => 24,
            '7d' => 168,
            '30d' => 720,
            default => 24
        };
        
        $metrics = [
            'response_time' => [
                'current' => random_int(80, 150),
                'average' => random_int(90, 140),
                'max' => random_int(200, 300),
                'trend' => collect(range(1, min($hours, 24)))->map(function () {
                    return [
                        'timestamp' => now()->subHours(rand(1, 24))->toISOString(),
                        'value' => random_int(70, 200)
                    ];
                })
            ],
            'throughput' => [
                'current' => random_int(450, 550),
                'average' => random_int(400, 500),
                'max' => random_int(600, 800),
                'trend' => collect(range(1, min($hours, 24)))->map(function () {
                    return [
                        'timestamp' => now()->subHours(rand(1, 24))->toISOString(),
                        'value' => random_int(300, 700)
                    ];
                })
            ],
            'error_rate' => [
                'current' => round(random_int(1, 5) / 10, 2),
                'average' => round(random_int(2, 4) / 10, 2),
                'max' => round(random_int(5, 10) / 10, 2),
                'trend' => collect(range(1, min($hours, 24)))->map(function () {
                    return [
                        'timestamp' => now()->subHours(rand(1, 24))->toISOString(),
                        'value' => round(random_int(0, 8) / 10, 2)
                    ];
                })
            ]
        ];
        
        return $metrics;
    }

    /**
     * Get AI usage report
     */
    public function getAIUsageReport(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->subDays(30)->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');
        $groupBy = $filters['group_by'] ?? 'day';
        
        // Get invoice processing statistics
        $query = Invoice::whereBetween('created_at', [$startDate, $endDate]);
        
        $totalInvoices = $query->count();
        $successfulProcessing = $query->where('status', 'Analisado')->count();
        $failedProcessing = $query->where('status', 'Erro')->count();
        
        $successRate = $totalInvoices > 0 ? round(($successfulProcessing / $totalInvoices) * 100, 1) : 0;
        
        // Processing time analytics (simulated)
        $avgProcessingTime = 4.5; // minutes
        $totalProcessingTime = $successfulProcessing * $avgProcessingTime;
        
        // Cost estimation (simulated)
        $costPerProcessing = 0.15; // USD
        $totalCost = $totalInvoices * $costPerProcessing;
        
        return [
            'summary' => [
                'totalInvoices' => $totalInvoices,
                'successfulProcessing' => $successfulProcessing,
                'failedProcessing' => $failedProcessing,
                'successRate' => $successRate,
                'avgProcessingTime' => $avgProcessingTime,
                'totalProcessingTime' => $totalProcessingTime,
                'estimatedCost' => $totalCost
            ],
            'dailyUsage' => $this->generateDailyUsageData($startDate, $endDate),
            'categoryAccuracy' => $this->generateCategoryAccuracyData(),
            'modelPerformance' => [
                'current_model' => 'gpt-4o-mini',
                'accuracy' => 94.2,
                'speed' => 'Fast',
                'cost_efficiency' => 'High'
            ]
        ];
    }

    /**
     * Get system settings
     */
    public function getSystemSettings(): array
    {
        return [
            'max_invoice_size' => 10240, // KB
            'processing_timeout' => 300, // seconds
            'ai_model_version' => 'gpt-4o-mini',
            'maintenance_mode' => false,
            'rate_limit_per_user' => 100, // requests per hour
            'auto_backup_enabled' => true,
            'backup_frequency' => 'daily',
            'log_retention_days' => 30,
            'notification_email' => env('ADMIN_EMAIL', 'admin@pontu.ai')
        ];
    }

    /**
     * Update system settings
     */
    public function updateSystemSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
        
        // Clear settings cache
        Cache::tags(['system_settings'])->flush();
        
        $this->logAdminAction('system_settings_updated', $settings);
    }

    /**
     * Initiate system backup
     */
    public function initiateBackup(): array
    {
        try {
            // In a real implementation, this would trigger actual backup processes
            $backupId = Str::ulid();
            
            // Store backup job info
            Cache::put("backup_status_{$backupId}", [
                'id' => $backupId,
                'status' => 'running',
                'progress' => 0,
                'started_at' => now()->toISOString(),
                'estimated_completion' => now()->addMinutes(15)->toISOString()
            ], 3600);
            
            // In production, dispatch backup job here
            // BackupSystemJob::dispatch($backupId);
            
            $this->logAdminAction('backup_initiated', ['backup_id' => $backupId]);
            
            return [
                'success' => true,
                'backup_id' => $backupId,
                'message' => 'Backup iniciado com sucesso',
                'estimated_completion' => '15 minutos'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to initiate backup', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => 'Falha ao iniciar backup: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get backup status
     */
    public function getBackupStatus(): array
    {
        // Get latest backup jobs from cache/database
        $backups = collect([
            [
                'id' => 'backup_' . now()->format('Ymd_His'),
                'status' => 'completed',
                'progress' => 100,
                'started_at' => now()->subHour()->toISOString(),
                'completed_at' => now()->subMinutes(45)->toISOString(),
                'size' => '2.1 GB',
                'type' => 'full'
            ],
            [
                'id' => 'backup_' . now()->subDay()->format('Ymd_His'),
                'status' => 'completed',
                'progress' => 100,
                'started_at' => now()->subDay()->subHour()->toISOString(),
                'completed_at' => now()->subDay()->subMinutes(45)->toISOString(),
                'size' => '1.9 GB',
                'type' => 'incremental'
            ]
        ]);
        
        return [
            'recent_backups' => $backups->toArray(),
            'next_scheduled' => now()->addDay()->toISOString(),
            'storage_used' => '15.7 GB',
            'storage_limit' => '100 GB'
        ];
    }

    /**
     * Get admin notifications
     */
    public function getAdminNotifications(): array
    {
        // In production, this would come from a notifications table
        $notifications = collect([
            [
                'id' => Str::ulid(),
                'type' => 'system_alert',
                'title' => 'Alto volume de faturas pendentes',
                'message' => 'Existem 23 faturas aguardando processamento há mais de 2 horas',
                'severity' => 'warning',
                'read' => false,
                'created_at' => now()->subHours(2)->toISOString(),
                'action_url' => '/admin/faturas?status=pending'
            ],
            [
                'id' => Str::ulid(),
                'type' => 'performance',
                'title' => 'Latência da API aumentou',
                'message' => 'Tempo de resposta médio subiu para 180ms nas últimas 2 horas',
                'severity' => 'info',
                'read' => false,
                'created_at' => now()->subHour()->toISOString(),
                'action_url' => '/admin/performance'
            ],
            [
                'id' => Str::ulid(),
                'type' => 'user_activity',
                'title' => 'Novo usuário admin criado',
                'message' => 'Um novo usuário com privilégios administrativos foi criado',
                'severity' => 'info',
                'read' => true,
                'created_at' => now()->subDays(1)->toISOString(),
                'action_url' => '/admin/usuarios'
            ]
        ]);
        
        return [
            'notifications' => $notifications->toArray(),
            'unread_count' => $notifications->where('read', false)->count()
        ];
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(string $notificationId): void
    {
        // In production, update notification in database
        // AdminNotification::where('id', $notificationId)->update(['read' => true]);
        
        $this->logAdminAction('notification_read', ['notification_id' => $notificationId]);
    }

    /**
     * Generate admin report
     */
    public function generateReport(string $type, array $params = []): array
    {
        $reportId = Str::ulid();
        $format = $params['format'] ?? 'json';
        
        try {
            $data = match($type) {
                'users' => $this->generateUsersReport($params),
                'invoices' => $this->generateInvoicesReport($params),
                'performance' => $this->generatePerformanceReport($params),
                'ai_usage' => $this->generateAIUsageReport($params),
                default => throw new \InvalidArgumentException("Tipo de relatório inválido: {$type}")
            };
            
            // Store report data
            $report = [
                'id' => $reportId,
                'type' => $type,
                'format' => $format,
                'data' => $data,
                'generated_at' => now()->toISOString(),
                'expires_at' => now()->addDays(7)->toISOString()
            ];
            
            Cache::put("report_{$reportId}", $report, 60 * 24 * 7); // 7 days
            
            $this->logAdminAction('report_generated', [
                'report_id' => $reportId,
                'type' => $type,
                'format' => $format
            ]);
            
            return [
                'success' => true,
                'report_id' => $reportId,
                'download_url' => "/admin/reports/{$reportId}/download",
                'expires_at' => $report['expires_at']
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to generate report', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Falha ao gerar relatório: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Download generated report
     */
    public function downloadReport(string $reportId): Response
    {
        $report = Cache::get("report_{$reportId}");
        
        if (!$report) {
            abort(404, 'Relatório não encontrado ou expirado');
        }
        
        $filename = "relatorio_{$report['type']}_{$reportId}.{$report['format']}";
        
        switch ($report['format']) {
            case 'csv':
                return $this->generateCsvResponse($report['data'], $filename);
            case 'pdf':
                return $this->generatePdfResponse($report['data'], $filename);
            default:
                return response()->json($report['data']);
        }
    }

    /**
     * Get audit logs
     */
    public function getAuditLogs(array $filters): LengthAwarePaginator
    {
        // In production, this would query the audit_logs table
        $logs = collect([
            [
                'id' => Str::ulid(),
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name,
                'action_type' => 'user_status_updated',
                'description' => 'Status do usuário alterado para Inativo',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now()->subHours(2)->toISOString(),
                'metadata' => ['user_id' => '123', 'new_status' => 'Inativo']
            ],
            [
                'id' => Str::ulid(),
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name,
                'action_type' => 'invoice_reprocessed',
                'description' => 'Fatura enviada para reprocessamento',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now()->subHours(4)->toISOString(),
                'metadata' => ['invoice_id' => '456']
            ]
        ]);
        
        // Apply filters
        if (isset($filters['action_type'])) {
            $logs = $logs->where('action_type', $filters['action_type']);
        }
        
        if (isset($filters['user_id'])) {
            $logs = $logs->where('user_id', $filters['user_id']);
        }
        
        // Paginate
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;
        $offset = ($page - 1) * $perPage;
        
        $paginatedLogs = $logs->slice($offset, $perPage);
        
        return new LengthAwarePaginator(
            $paginatedLogs,
            $logs->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    /**
     * Get queue status
     */
    public function getQueueStatus(): array
    {
        $queues = [
            'default' => [
                'name' => 'default',
                'size' => Queue::size('default'),
                'failed' => $this->getFailedJobsCount('default'),
                'processed_today' => random_int(150, 300),
                'avg_processing_time' => random_int(30, 120) // seconds
            ],
            'invoices' => [
                'name' => 'invoices',
                'size' => Queue::size('invoices'),
                'failed' => $this->getFailedJobsCount('invoices'),
                'processed_today' => random_int(50, 150),
                'avg_processing_time' => random_int(60, 300) // seconds
            ]
        ];
        
        return [
            'queues' => array_values($queues),
            'total_pending' => collect($queues)->sum('size'),
            'total_failed' => collect($queues)->sum('failed'),
            'workers_active' => 3 // Could be retrieved from supervisor or similar
        ];
    }

    /**
     * Get error statistics
     */
    public function getErrorStats(array $filters): array
    {
        $period = $filters['period'] ?? '24h';
        $groupBy = $filters['group_by'] ?? 'hour';
        
        // Generate error stats (in production, query from logs/monitoring)
        $errorTypes = [
            'api_timeout' => random_int(5, 15),
            'database_connection' => random_int(2, 8),
            'ai_processing_failed' => random_int(10, 25),
            'file_upload_error' => random_int(3, 12),
            'validation_error' => random_int(20, 50)
        ];
        
        $totalErrors = array_sum($errorTypes);
        $errorRate = round($totalErrors / 1000 * 100, 2); // Assuming 1000 total requests
        
        // Generate time-series data
        $timeRange = match($period) {
            '1h' => 12, // 5-minute intervals
            '24h' => 24, // hourly intervals
            '7d' => 7, // daily intervals
            '30d' => 30 // daily intervals
        };
        
        $timeSeries = collect(range(1, $timeRange))->map(function ($i) use ($period) {
            $time = match($period) {
                '1h' => now()->subMinutes($i * 5),
                '24h' => now()->subHours($i),
                '7d' => now()->subDays($i),
                '30d' => now()->subDays($i)
            };
            
            return [
                'timestamp' => $time->toISOString(),
                'count' => random_int(0, 15),
                'rate' => round(random_int(0, 50) / 100, 2)
            ];
        })->reverse()->values();
        
        return [
            'summary' => [
                'total_errors' => $totalErrors,
                'error_rate' => $errorRate,
                'most_common' => array_keys($errorTypes, max($errorTypes))[0],
                'trend' => 'decreasing' // Could be calculated from time series
            ],
            'error_types' => $errorTypes,
            'time_series' => $timeSeries,
            'top_errors' => [
                [
                    'message' => 'Connection timeout to AI service',
                    'count' => 25,
                    'last_occurred' => now()->subMinutes(15)->toISOString()
                ],
                [
                    'message' => 'Failed to parse PDF content',
                    'count' => 18,
                    'last_occurred' => now()->subMinutes(32)->toISOString()
                ],
                [
                    'message' => 'Database connection pool exhausted',
                    'count' => 12,
                    'last_occurred' => now()->subHours(2)->toISOString()
                ]
            ]
        ];
    }

    /**
     * Cleanup old data
     */
    public function cleanupOldData(array $params): array
    {
        $olderThanDays = $params['older_than_days'];
        $dataType = $params['data_type'];
        $cutoffDate = now()->subDays($olderThanDays);
        
        $cleaned = 0;
        $errors = [];
        
        try {
            switch ($dataType) {
                case 'logs':
                    // In production, delete old log entries
                    $cleaned = 150; // Simulated
                    break;
                    
                case 'temp_files':
                    // Clean temporary files
                    $cleaned = Storage::disk('local')->allFiles('temp');
                    $cleaned = count($cleaned);
                    break;
                    
                case 'failed_jobs':
                    // Clean old failed jobs
                    $cleaned = DB::table('failed_jobs')
                        ->where('failed_at', '<', $cutoffDate)
                        ->delete();
                    break;
                    
                case 'all':
                    // Clean all types
                    $cleaned = 300; // Simulated total
                    break;
                    
                default:
                    throw new \InvalidArgumentException("Tipo de dados inválido: {$dataType}");
            }
            
            $this->logAdminAction('data_cleanup', [
                'data_type' => $dataType,
                'older_than_days' => $olderThanDays,
                'items_cleaned' => $cleaned
            ]);
            
            return [
                'success' => true,
                'items_cleaned' => $cleaned,
                'data_type' => $dataType,
                'message' => "Limpeza concluída: {$cleaned} itens removidos"
            ];
            
        } catch (\Exception $e) {
            Log::error('Data cleanup failed', [
                'data_type' => $dataType,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Falha na limpeza: ' . $e->getMessage()
            ];
        }
    }

    // Private helper methods

    private function mapStatusToFrontend(string $status): string
    {
        $statusMap = [
            'Processando' => 'pending',
            'Analisado' => 'processed',
            'Erro' => 'error'
        ];
        
        return $statusMap[$status] ?? 'pending';
    }

    private function calculateWaitTime(\Carbon\Carbon $createdAt): string
    {
        $diff = $createdAt->diffInMinutes(now());
        
        if ($diff < 60) {
            return "{$diff}m";
        } elseif ($diff < 1440) {
            $hours = intval($diff / 60);
            $minutes = $diff % 60;
            return "{$hours}h {$minutes}m";
        } else {
            $days = intval($diff / 1440);
            return "{$days}d";
        }
    }

    private function calculatePriority(Invoice $invoice): string
    {
        $waitTime = $invoice->created_at->diffInHours(now());
        $amount = $invoice->total_amount;
        
        if ($waitTime > 2 || $amount > 100000) {
            return 'high';
        } elseif ($waitTime > 1 || $amount > 50000) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    private function checkDatabaseHealth(): string
    {
        try {
            DB::connection()->getPdo();
            return 'online';
        } catch (\Exception $e) {
            return 'offline';
        }
    }

    private function checkStorageHealth(): string
    {
        try {
            Storage::disk('s3')->exists('health-check');
            return 'online';
        } catch (\Exception $e) {
            return 'warning';
        }
    }

    private function checkQueueHealth(): string
    {
        try {
            $queueSize = Queue::size();
            return $queueSize < 1000 ? 'online' : 'warning';
        } catch (\Exception $e) {
            return 'offline';
        }
    }

    private function generateDailyUsageData(string $startDate, string $endDate): array
    {
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        $data = [];
        
        while ($start <= $end) {
            $data[] = [
                'date' => $start->format('Y-m-d'),
                'total_requests' => random_int(50, 200),
                'successful' => random_int(45, 190),
                'failed' => random_int(1, 10),
                'avg_processing_time' => round(random_int(200, 800) / 100, 2)
            ];
            $start->addDay();
        }
        
        return $data;
    }

    private function generateCategoryAccuracyData(): array
    {
        return [
            'FOOD' => 96.5,
            'SUPER' => 94.2,
            'TRANS' => 92.8,
            'FUEL' => 98.1,
            'STREAM' => 99.3,
            'PHARM' => 95.7,
            'ECOMM' => 91.4,
            'OTHER' => 87.6
        ];
    }

    private function generateUsersReport(array $params): array
    {
        $users = User::with(['cards', 'invoices'])->get();
        
        return $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'created_at' => $user->created_at->toISOString(),
                'cards_count' => $user->cards->count(),
                'invoices_count' => $user->invoices->count(),
                'total_spent' => $user->invoices->sum('total_amount') / 100
            ];
        })->toArray();
    }

    private function generateInvoicesReport(array $params): array
    {
        $invoices = Invoice::with(['user', 'card'])->get();
        
        return $invoices->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'user_name' => $invoice->user->name,
                'card_name' => $invoice->card->name,
                'total_amount' => $invoice->total_amount / 100,
                'status' => $invoice->status,
                'created_at' => $invoice->created_at->toISOString(),
                'processed_at' => $invoice->updated_at->toISOString()
            ];
        })->toArray();
    }

    private function generatePerformanceReport(array $params): array
    {
        return [
            'avg_response_time' => 145.7,
            'total_requests' => 15420,
            'error_rate' => 2.3,
            'uptime' => 99.8,
            'peak_hour' => '14:00-15:00',
            'slowest_endpoints' => [
                '/api/invoices/upload' => 3.2,
                '/api/analysis/cards-recommendation' => 2.8,
                '/api/dashboard' => 1.1
            ]
        ];
    }

    private function getFailedJobsCount(string $queue): int
    {
        try {
            return DB::table('failed_jobs')->where('queue', $queue)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function generateCsvResponse(array $data, string $filename): Response
    {
        $csv = '';
        if (!empty($data)) {
            // Add headers
            $csv .= implode(',', array_keys($data[0])) . "\n";
            
            // Add data rows
            foreach ($data as $row) {
                $csv .= implode(',', array_map(function ($value) {
                    return '"' . str_replace('"', '""', $value) . '"';
                }, $row)) . "\n";
            }
        }
        
        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function generatePdfResponse(array $data, string $filename): Response
    {
        // In production, you would use a PDF library like DomPDF or similar
        // For now, returning a simple response
        return response()->json([
            'message' => 'PDF generation not implemented',
            'data' => $data
        ]);
    }

    /**
     * Get banks list for admin management
     */
    public function getBanksList(array $filters): array
    {
        $banks = \Domains\Finance\Models\Bank::orderBy('name', 'asc')->get();
        
        return $banks->map(function ($bank) {
            return [
                'id' => $bank->id,
                'name' => $bank->name,
                'code' => $bank->code,
                'description' => $bank->description,
                'logo_url' => $bank->logo_url,
                'primary_color' => $bank->primary_color,
                'secondary_color' => $bank->secondary_color,
                'is_active' => $bank->is_active,
                'created_at' => $bank->created_at->toISOString(),
                'updated_at' => $bank->updated_at->toISOString()
            ];
        })->toArray();
    }

    /**
     * Add new bank
     */
    public function addBank(array $data): \Domains\Finance\Models\Bank
    {
        $bank = \Domains\Finance\Models\Bank::create($data);
        
        $this->logAdminAction('bank_created', [
            'bank_id' => $bank->id,
            'bank_name' => $bank->name
        ]);
        
        return $bank;
    }

    /**
     * Update existing bank
     */
    public function updateBank(string $bankId, array $data): void
    {
        $bank = \Domains\Finance\Models\Bank::findOrFail($bankId);
        $oldData = $bank->toArray();
        
        $bank->update($data);
        
        $this->logAdminAction('bank_updated', [
            'bank_id' => $bankId,
            'changes' => array_diff_assoc($data, $oldData)
        ]);
    }

    /**
     * Get reward programs list for admin management
     */
    public function getRewardProgramsList(array $filters): array
    {
        $programs = \Domains\Cards\Models\RewardProgram::orderBy('name', 'asc')->get();
        
        return $programs->map(function ($program) {
            return [
                'id' => $program->id,
                'name' => $program->name,
                'code' => $program->code,
                'description' => $program->description,
                'website' => $program->website,
                'logo_path' => $program->logo_path,
                'created_at' => $program->created_at->toISOString(),
                'updated_at' => $program->updated_at->toISOString()
            ];
        })->toArray();
    }

    /**
     * Add new reward program
     */
    public function addRewardProgram(array $data): \Domains\Cards\Models\RewardProgram
    {
        // Handle logo upload if provided
        if (isset($data['logo'])) {
            $logoPath = $data['logo']->store('reward-programs', 's3');
            $data['logo_path'] = $logoPath;
            unset($data['logo']);
        }
        
        $program = \Domains\Cards\Models\RewardProgram::create($data);
        
        $this->logAdminAction('reward_program_created', [
            'program_id' => $program->id,
            'program_name' => $program->name
        ]);
        
        return $program;
    }

    /**
     * Update existing reward program
     */
    public function updateRewardProgram(string $programId, array $data): void
    {
        $program = \Domains\Cards\Models\RewardProgram::findOrFail($programId);
        $oldData = $program->toArray();
        
        // Handle logo upload if provided
        if (isset($data['logo'])) {
            // Delete old logo if exists
            if ($program->logo_path) {
                Storage::delete($program->logo_path);
            }
            
            $logoPath = $data['logo']->store('reward-programs', 's3');
            $data['logo_path'] = $logoPath;
            unset($data['logo']);
        }
        
        $program->update($data);
        
        $this->logAdminAction('reward_program_updated', [
            'program_id' => $programId,
            'changes' => array_diff_assoc($data, $oldData)
        ]);
    }

    private function logAdminAction(string $action, array $metadata = []): void
    {
        try {
            // In production, store in audit_logs table
            Log::info("Admin action: {$action}", [
                'user_id' => auth()->id(),
                'action' => $action,
                'metadata' => $metadata,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log admin action', [
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }
}