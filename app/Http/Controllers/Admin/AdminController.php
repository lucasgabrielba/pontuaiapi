<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Domains\Admin\Services\AdminService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    protected AdminService $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    /**
     * Get admin dashboard statistics
     */
    public function getStats(Request $request)
    {
        $stats = $this->adminService->getStats();
        return response()->json($stats);
    }

    /**
     * Get list of users with admin filters
     */
    public function getUsers(Request $request)
    {
        $filters = $request->all();
        $users = $this->adminService->getUsers($filters);
        return response()->json($users);
    }

    /**
     * Get user details by ID
     */
    public function getUserDetails(Request $request, string $userId)
    {
        $user = $this->adminService->getUserDetails($userId);
        return response()->json($user);
    }

    /**
     * Update user status
     */
    public function updateUserStatus(Request $request, string $userId)
    {
        $request->validate([
            'status' => 'required|string|in:Ativo,Inativo'
        ]);

        $this->adminService->updateUserStatus($userId, $request->status);
        
        return response()->json([
            'message' => 'Status do usuário atualizado com sucesso'
        ]);
    }

    /**
     * Get pending invoices for admin review
     */
    public function getPendingInvoices(Request $request)
    {
        $filters = $request->all();
        $invoices = $this->adminService->getPendingInvoices($filters);
        return response()->json($invoices);
    }

    /**
     * Reprocess a failed invoice
     */
    public function reprocessInvoice(Request $request, string $invoiceId)
    {
        $result = $this->adminService->reprocessInvoice($invoiceId);
        return response()->json($result);
    }

    /**
     * Prioritize invoice processing
     */
    public function prioritizeInvoice(Request $request, string $invoiceId)
    {
        $request->validate([
            'priority' => 'required|string|in:low,medium,high'
        ]);

        $this->adminService->prioritizeInvoice($invoiceId, $request->priority);
        
        return response()->json([
            'message' => 'Prioridade da fatura atualizada com sucesso'
        ]);
    }

    /**
     * Get system health information
     */
    public function getSystemHealth(Request $request)
    {
        $health = $this->adminService->getSystemHealth();
        return response()->json($health);
    }

    /**
     * Get recent system activities
     */
    public function getRecentActivities(Request $request)
    {
        $filters = $request->all();
        $activities = $this->adminService->getRecentActivities($filters);
        return response()->json($activities);
    }

    /**
     * Get system logs
     */
    public function getSystemLogs(Request $request)
    {
        $filters = $request->all();
        $logs = $this->adminService->getSystemLogs($filters);
        return response()->json($logs);
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(Request $request)
    {
        $filters = $request->all();
        $metrics = $this->adminService->getPerformanceMetrics($filters);
        return response()->json($metrics);
    }

    /**
     * Get AI usage report
     */
    public function getAIUsageReport(Request $request)
    {
        $filters = $request->all();
        $report = $this->adminService->getAIUsageReport($filters);
        return response()->json($report);
    }

    /**
     * Get system settings
     */
    public function getSystemSettings(Request $request)
    {
        $settings = $this->adminService->getSystemSettings();
        return response()->json($settings);
    }

    /**
     * Update system settings
     */
    public function updateSystemSettings(Request $request)
    {
        $request->validate([
            'max_invoice_size' => 'sometimes|integer|min:1',
            'processing_timeout' => 'sometimes|integer|min:1',
            'ai_model_version' => 'sometimes|string',
            'maintenance_mode' => 'sometimes|boolean',
            'rate_limit_per_user' => 'sometimes|integer|min:1'
        ]);

        $this->adminService->updateSystemSettings($request->validated());
        
        return response()->json([
            'message' => 'Configurações do sistema atualizadas com sucesso'
        ]);
    }

    /**
     * Initiate system backup
     */
    public function initiateBackup(Request $request)
    {
        $result = $this->adminService->initiateBackup();
        return response()->json($result);
    }

    /**
     * Get backup status
     */
    public function getBackupStatus(Request $request)
    {
        $status = $this->adminService->getBackupStatus();
        return response()->json($status);
    }

    /**
     * Get admin notifications
     */
    public function getAdminNotifications(Request $request)
    {
        $notifications = $this->adminService->getAdminNotifications();
        return response()->json($notifications);
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(Request $request, string $notificationId)
    {
        $this->adminService->markNotificationAsRead($notificationId);
        
        return response()->json([
            'message' => 'Notificação marcada como lida'
        ]);
    }

    /**
     * Generate admin report
     */
    public function generateReport(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:users,invoices,performance,ai_usage',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'format' => 'sometimes|string|in:json,csv,pdf'
        ]);

        $result = $this->adminService->generateReport(
            $request->type,
            $request->only(['start_date', 'end_date', 'format'])
        );
        
        return response()->json($result);
    }

    /**
     * Download generated report
     */
    public function downloadReport(Request $request, string $reportId)
    {
        return $this->adminService->downloadReport($reportId);
    }

    /**
     * Get audit logs
     */
    public function getAuditLogs(Request $request)
    {
        $filters = $request->all();
        $logs = $this->adminService->getAuditLogs($filters);
        return response()->json($logs);
    }

    /**
     * Get queue status
     */
    public function getQueueStatus(Request $request)
    {
        $status = $this->adminService->getQueueStatus();
        return response()->json($status);
    }

    /**
     * Get error statistics
     */
    public function getErrorStats(Request $request)
    {
        $filters = $request->all();
        $stats = $this->adminService->getErrorStats($filters);
        return response()->json($stats);
    }

    /**
     * Cleanup old data
     */
    public function cleanupOldData(Request $request)
    {
        $request->validate([
            'older_than_days' => 'required|integer|min:1',
            'data_type' => 'required|string|in:logs,temp_files,failed_jobs,all'
        ]);

        $result = $this->adminService->cleanupOldData($request->validated());
        return response()->json($result);
    }
}