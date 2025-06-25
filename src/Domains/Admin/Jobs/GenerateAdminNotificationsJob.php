<?php

namespace Domains\Admin\Jobs;

use Domains\Finance\Models\Invoice;
use Domains\Shared\Models\Notification;
use Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAdminNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Gerando notificações administrativas automáticas');

            $this->checkPendingInvoices();
            $this->checkSystemPerformance();
            $this->checkErrorRates();
            $this->checkUserActivity();
            $this->checkStorageUsage();

            Log::info('Notificações administrativas geradas com sucesso');
        } catch (\Exception $e) {
            Log::error('Erro ao gerar notificações administrativas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Check for invoices pending too long
     */
    private function checkPendingInvoices(): void
    {
        $pendingInvoices = Invoice::where('status', 'Processando')
            ->where('created_at', '<=', now()->subHours(2))
            ->count();

        if ($pendingInvoices > 20) {
            $this->createNotification(
                'system_alert',
                'Alto volume de faturas pendentes',
                "Existem {$pendingInvoices} faturas aguardando processamento há mais de 2 horas",
                'warning',
                '/admin/faturas?status=pending'
            );
        }
    }

    /**
     * Check system performance metrics
     */
    private function checkSystemPerformance(): void
    {
        // Simulated performance check - in production, this would check real metrics
        $avgResponseTime = random_int(80, 300);
        
        if ($avgResponseTime > 200) {
            $this->createNotification(
                'performance',
                'Latência da API aumentou',
                "Tempo de resposta médio subiu para {$avgResponseTime}ms nas últimas 2 horas",
                'info',
                '/admin/performance'
            );
        }
    }

    /**
     * Check error rates
     */
    private function checkErrorRates(): void
    {
        $errorInvoices = Invoice::where('status', 'Erro')
            ->where('updated_at', '>=', now()->subHour())
            ->count();

        $totalInvoices = Invoice::where('updated_at', '>=', now()->subHour())->count();
        
        if ($totalInvoices > 0) {
            $errorRate = ($errorInvoices / $totalInvoices) * 100;
            
            if ($errorRate > 10) {
                $this->createNotification(
                    'system_alert',
                    'Alta taxa de erro no processamento',
                    "Taxa de erro de {$errorRate}% na última hora ({$errorInvoices} de {$totalInvoices} faturas)",
                    'error',
                    '/admin/invoices?status=error'
                );
            }
        }
    }

    /**
     * Check user activity
     */
    private function checkUserActivity(): void
    {
        $newUsersToday = User::whereDate('created_at', today())->count();
        
        if ($newUsersToday > 50) {
            $this->createNotification(
                'user_activity',
                'Alto volume de novos usuários',
                "{$newUsersToday} novos usuários registrados hoje",
                'success',
                '/admin/usuarios?filter=today'
            );
        }

        // Check for admin user creation
        $newAdminUsers = User::whereDate('created_at', today())
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['admin', 'super_admin']);
            })
            ->count();

        if ($newAdminUsers > 0) {
            $this->createNotification(
                'security',
                'Novo usuário admin criado',
                "{$newAdminUsers} usuário(s) com privilégios administrativos criado(s) hoje",
                'warning',
                '/admin/usuarios?role=admin'
            );
        }
    }

    /**
     * Check storage usage
     */
    private function checkStorageUsage(): void
    {
        // Simulated storage check - in production, check real storage usage
        $storageUsagePercent = random_int(60, 95);
        
        if ($storageUsagePercent > 85) {
            $severity = $storageUsagePercent > 90 ? 'error' : 'warning';
            
            $this->createNotification(
                'system_alert',
                'Alto uso de armazenamento',
                "Uso de armazenamento em {$storageUsagePercent}%. Considere fazer limpeza de arquivos antigos.",
                $severity,
                '/admin/storage'
            );
        }
    }

    /**
     * Create a new admin notification
     */
    private function createNotification(
        string $type,
        string $title,
        string $message,
        string $severity = 'info',
        ?string $actionUrl = null
    ): void {
        // Check if similar notification already exists in the last 24 hours
        $existingNotification = Notification::where('type', $type)
            ->where('title', $title)
            ->where('created_at', '>=', now()->subDay())
            ->first();

        if (!$existingNotification) {
            Notification::create([
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'severity' => $severity,
                'action_url' => $actionUrl,
                'read' => false
            ]);

            Log::info('Notificação administrativa criada', [
                'type' => $type,
                'title' => $title,
                'severity' => $severity
            ]);
        }
    }
}