<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreInvoiceRequest;
use App\Http\Requests\Finance\UploadInvoiceRequest;
use Domains\Finance\Services\InvoicesService;
use Illuminate\Http\Request;

class InvoicesController extends Controller
{
    protected InvoicesService $invoicesService;

    public function __construct(InvoicesService $invoicesService)
    {
        $this->invoicesService = $invoicesService;
    }

    /**
     * Display a listing of the invoices.
     */
    public function index(Request $request)
    {
        $filters = $request->all();
        $invoices = $this->invoicesService->list($filters);

        return response()->json($invoices);
    }

    /**
     * Store a newly created invoice in storage.
     */
    public function store(StoreInvoiceRequest $request)
    {
        $data = $request->validated();
        $invoice = $this->invoicesService->create($data);

        return response()->json($invoice, 201);
    }

    public function show(string $invoiceId)
    {
        $invoice = $this->invoicesService->get($invoiceId);
        return response()->json($invoice);
    }

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
            $search ?? '',
            $sortField,
            $sortOrder,
            $categoryFilter
        );

        return response()->json($transactions);
    }

    public function getCategorySummary(string $invoiceId)
    {
        $summaryByCategory = $this->invoicesService->getSummaryByCategory($invoiceId);
        return response()->json($summaryByCategory);
    }

    public function update(Request $request, string $invoiceId)
    {
        $data = $request->all();
        $this->invoicesService->update($invoiceId, $data);

        return response()->json([
            'message' => 'Fatura atualizada com sucesso',
        ]);
    }

    /**
     * Remove the specified invoice from storage.
     */
    public function destroy(string $invoiceId)
    {
        $this->invoicesService->destroy($invoiceId);

        return response()->json([
            'message' => 'Fatura deletada com sucesso',
        ], 204);
    }

    /**
     * Upload invoice file for processing.
     */
    public function upload(UploadInvoiceRequest $request)
    {
        $result = $this->invoicesService->uploadInvoice($request->file('invoice_file'), $request->card_id);

        return response()->json([
            'message' => 'Fatura enviada para processamento',
            'data' => $result
        ], 200);
    }

    /**
     * Get invoice transactions.
     */
    public function transactions(string $invoiceId)
    {
        $transactions = $this->invoicesService->getTransactions($invoiceId);

        return response()->json($transactions);
    }
}