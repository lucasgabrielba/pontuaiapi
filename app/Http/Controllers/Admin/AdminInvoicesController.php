<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Domains\Admin\Services\AdminInvoicesService;
use Illuminate\Http\Request;

class AdminInvoicesController extends Controller
{
    protected AdminInvoicesService $adminInvoicesService;

    public function __construct(AdminInvoicesService $adminInvoicesService)
    {
        $this->adminInvoicesService = $adminInvoicesService;
    }

    public function getInvoiceSuggestions(string $invoiceId)
    {
        $suggestions = $this->adminInvoicesService->getInvoiceSuggestions($invoiceId);
        return response()->json($suggestions);
    }

    /**
     * Create a new suggestion for an invoice
     */
    public function createSuggestion(Request $request, string $invoiceId)
    {
        $validatedData = $request->validate([
            'category_id' => 'nullable|string|exists:categories,id',
            'type' => 'required|string|in:card_recommendation,merchant_recommendation,category_optimization,points_strategy,general_tip',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'recommendation' => 'required|string',
            'impact_description' => 'nullable|string',
            'potential_points_increase' => 'nullable|string',
            'priority' => 'required|string|in:low,medium,high',
            'is_personalized' => 'boolean',
            'applies_to_future' => 'boolean',
        ]);

        $suggestion = $this->adminInvoicesService->createSuggestion($invoiceId, $validatedData);

        return response()->json($suggestion, 201);
    }

    /**
     * Update an existing suggestion
     */
    public function updateSuggestion(Request $request, string $invoiceId, string $suggestionId)
    {
        $validatedData = $request->validate([
            'category_id' => 'nullable|string|exists:categories,id',
            'type' => 'sometimes|string|in:card_recommendation,merchant_recommendation,category_optimization,points_strategy,general_tip',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'recommendation' => 'sometimes|string',
            'impact_description' => 'nullable|string',
            'potential_points_increase' => 'nullable|string',
            'priority' => 'sometimes|string|in:low,medium,high',
            'is_personalized' => 'boolean',
            'applies_to_future' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $this->adminInvoicesService->updateSuggestion($suggestionId, $validatedData);

        return response()->json(['message' => 'Sugestão atualizada com sucesso']);
    }

    /**
     * Delete a suggestion
     */
    public function deleteSuggestion(string $invoiceId, string $suggestionId)
    {
        $this->adminInvoicesService->deleteSuggestion($suggestionId);

        return response()->json(['message' => 'Sugestão deletada com sucesso'], 204);
    }

    /**
     * Get invoice categories for category-based suggestions
     */
    public function getInvoiceCategoriesForSuggestions(string $invoiceId)
    {
        $categories = $this->adminInvoicesService->getInvoiceCategoriesForSuggestions($invoiceId);
        return response()->json($categories);
    }

    /**
     * Toggle suggestion active status
     */
    public function toggleSuggestionStatus(string $invoiceId, string $suggestionId)
    {
        $suggestion = $this->adminInvoicesService->toggleSuggestionStatus($suggestionId);
        return response()->json($suggestion);
    }

    public function getUsers(Request $request)
    {
        $filters = $request->all();
        $users = $this->adminInvoicesService->getUsers($filters);

        return response()->json($users);
    }

    /**
     * Obter faturas de um usuário específico
     */
    public function getUserInvoices(Request $request, string $userId)
    {
        $filters = $request->all();
        $invoices = $this->adminInvoicesService->getUserInvoices($userId, $filters);

        return response()->json($invoices);
    }

    /**
     * Obter detalhes de uma fatura específica
     */
    public function getInvoiceDetails(string $invoiceId)
    {
        $invoice = $this->adminInvoicesService->getInvoiceDetails($invoiceId);
        return response()->json($invoice);
    }

    /**
     * Obter transações de uma fatura específica
     */
    public function getInvoiceTransactions(Request $request, string $invoiceId)
    {
        $filters = $request->all();
        $transactions = $this->adminInvoicesService->getInvoiceTransactions($invoiceId, $filters);

        return response()->json($transactions);
    }

    /**
     * Obter resumo por categoria de uma fatura
     */
    public function getInvoiceCategorySummary(string $invoiceId)
    {
        $summary = $this->adminInvoicesService->getInvoiceCategorySummary($invoiceId);
        return response()->json($summary);
    }

    /**
     * Reprocessar uma fatura com erro
     */
    public function reprocessInvoice(string $invoiceId)
    {
        $result = $this->adminInvoicesService->reprocessInvoice($invoiceId);
        return response()->json($result);
    }

    /**
     * Atualizar status de uma fatura manualmente
     */
    public function updateInvoiceStatus(Request $request, string $invoiceId)
    {
        $request->validate([
            'status' => 'required|string|in:Analisado,Processando,Erro'
        ]);

        $this->adminInvoicesService->updateInvoiceStatus($invoiceId, $request->status);

        return response()->json([
            'message' => 'Status da fatura atualizado com sucesso'
        ]);
    }

    /**
     * Obter estatísticas gerais de faturas
     */
    public function getInvoicesStats()
    {
        $stats = $this->adminInvoicesService->getInvoicesStats();
        return response()->json($stats);
    }

    /**
     * Deletar fatura (apenas admin)
     */
    public function deleteInvoice(string $invoiceId)
    {
        $this->adminInvoicesService->deleteInvoice($invoiceId);

        return response()->json([
            'message' => 'Fatura deletada com sucesso'
        ], 204);
    }

}