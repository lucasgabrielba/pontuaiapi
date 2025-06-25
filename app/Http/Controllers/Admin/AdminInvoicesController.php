<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateSuggestionRequest;
use App\Http\Requests\Admin\UpdateSuggestionRequest;
use Domains\Finance\Services\InvoicesService;
use Domains\Admin\Services\AdminInvoicesService;
use Illuminate\Http\Request;

class AdminInvoicesController extends Controller
{
    protected InvoicesService $invoicesService;
    protected AdminInvoicesService $adminInvoicesService;

    public function __construct(
        InvoicesService $invoicesService,
        AdminInvoicesService $adminInvoicesService
    ) {
        $this->invoicesService = $invoicesService;
        $this->adminInvoicesService = $adminInvoicesService;
    }

    /**
     * Display a listing of all invoices (admin view).
     */
    public function index(Request $request)
    {
        $filters = $request->all();
        $invoices = $this->adminInvoicesService->listAllInvoices($filters);

        return response()->json($invoices);
    }

    /**
     * Display the specified invoice (admin view).
     */
    public function show(string $invoiceId)
    {
        $invoice = $this->adminInvoicesService->getInvoice($invoiceId);
        return response()->json($invoice);
    }

    /**
     * Get paginated transactions for a specific invoice (admin view).
     */
    public function getTransactions(Request $request, string $invoiceId)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search', '');
        $sortField = $request->input('sort_field', 'transaction_date');
        $sortOrder = $request->input('sort_order', 'desc');
        $categoryFilter = $request->input('category_filter', 'all');

        $transactions = $this->invoicesService->getPaginatedTransactions(
            $invoiceId,
            $page,
            $perPage,
            $search,
            $sortField,
            $sortOrder,
            $categoryFilter
        );

        return response()->json($transactions);
    }

    /**
     * Get category summary for a specific invoice (admin view).
     */
    public function getCategorySummary(string $invoiceId)
    {
        $summaryByCategory = $this->invoicesService->getSummaryByCategory($invoiceId);
        return response()->json($summaryByCategory);
    }

    /**
     * Get suggestions for a specific invoice.
     */
    public function getSuggestions(string $invoiceId)
    {
        $suggestions = $this->adminInvoicesService->getInvoiceSuggestions($invoiceId);
        return response()->json($suggestions);
    }

    /**
     * Create a new suggestion for an invoice.
     */
    public function createSuggestion(CreateSuggestionRequest $request, string $invoiceId)
    {
        $data = $request->validated();
        $suggestion = $this->adminInvoicesService->createSuggestion($invoiceId, $data);

        return response()->json($suggestion, 201);
    }

    /**
     * Update an existing suggestion.
     */
    public function updateSuggestion(UpdateSuggestionRequest $request, string $invoiceId, string $suggestionId)
    {
        $data = $request->validated();
        $this->adminInvoicesService->updateSuggestion($suggestionId, $data);

        return response()->json([
            'message' => 'Sugestão atualizada com sucesso',
        ]);
    }

    /**
     * Delete a suggestion.
     */
    public function deleteSuggestion(string $invoiceId, string $suggestionId)
    {
        $this->adminInvoicesService->deleteSuggestion($suggestionId);

        return response()->json([
            'message' => 'Sugestão deletada com sucesso',
        ], 204);
    }

    /**
     * Reprocess an invoice.
     */
    public function reprocessInvoice(string $invoiceId)
    {
        $result = $this->adminInvoicesService->reprocessInvoice($invoiceId);

        return response()->json([
            'message' => 'Fatura reprocessada com sucesso',
            'data' => $result
        ]);
    }

    /**
     * Export invoice data.
     */
    public function exportInvoice(string $invoiceId)
    {
        $exportData = $this->adminInvoicesService->exportInvoiceData($invoiceId);

        return response()->json($exportData);
    }

    /**
     * Get processing logs for an invoice.
     */
    public function getLogs(string $invoiceId)
    {
        $logs = $this->adminInvoicesService->getInvoiceLogs($invoiceId);

        return response()->json($logs);
    }

    /**
     * Mark invoice as problematic.
     */
    public function markAsProblematic(Request $request, string $invoiceId)
    {
        $reason = $request->input('reason', 'Marcado como problemático pelo administrador');
        $this->adminInvoicesService->markAsProblematic($invoiceId, $reason);

        return response()->json([
            'message' => 'Fatura marcada como problemática',
        ]);
    }
}