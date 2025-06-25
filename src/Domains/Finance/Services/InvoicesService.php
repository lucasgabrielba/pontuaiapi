<?php

namespace Domains\Finance\Services;

use Domains\Cards\Models\Card;
use Domains\Finance\Jobs\ProcessInvoiceJob;
use Domains\Finance\Models\Invoice;
use Domains\Finance\Models\Transaction;
use Domains\Shared\Helpers\ListDataHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoicesService
{
    protected TransactionsService $transactionsService;

    public function __construct(TransactionsService $transactionsService)
    {
        $this->transactionsService = $transactionsService;
    }

    public function list(array $filters): LengthAwarePaginator
    {
        $helper = new ListDataHelper(new Invoice);

        // Ensure only user's invoices are returned
        $filters['user_id'] = auth()->id();

        return $helper->list($filters);
    }

    public function get(string $invoiceId): Invoice
    {
        $query = Invoice::query()->with('card');

        if (!auth()->user()->hasRole(['admin', 'super_admin'])) {
            $query->where('user_id', auth()->id());
        }

        $invoice = $query->where('id', $invoiceId)->firstOrFail();

        return $invoice;
    }

    public function getPaginatedTransactions(
        string $invoiceId,
        int $page = 1,
        int $perPage = 15,
        string $search = '',
        string $sortField = 'transaction_date',
        string $sortOrder = 'desc',
        string $categoryFilter = 'all'
    ) {
        $invoice = Invoice::query()->with('card');
        
        if (!auth()->user()->hasRole(['admin', 'super_admin'])) {
            $invoice->where('user_id', auth()->id());
        }
        
        $invoice = $invoice->where('id', $invoiceId)->firstOrFail();
        
        $transactionModel = new Transaction();
        $listHelper = new ListDataHelper($transactionModel, $invoice);
        
        $filters = [
            'page' => $page,
            'per_page' => $perPage,
            'include' => 'category',
            'order' => ($sortOrder === 'desc' ? '-' : '') . $sortField,
        ];
        
        if (!empty($search)) {
            $filters['search'] = $search;
        }
        
        if ($categoryFilter !== 'all') {
            if ($categoryFilter === 'uncategorized') {
                $filters['category_id'] = null;
            } else {
                $filters['category_id'] = $categoryFilter;
            }
        }
        
        $paginator = $listHelper->list($filters);
        
        $transactions = $paginator->getCollection()->map(function ($transaction) {
            if ($transaction->category) {
                $transaction->category_icon = $transaction->category->icon;
                $transaction->category_color = $transaction->category->color;
            }
            return $transaction;
        });
        
        $paginator->setCollection($transactions);
        
        return $paginator;
    }

    public function getSummaryByCategory(string $invoiceId): array
    {
        $invoice = Invoice::query()->with('card');
        
        if (!auth()->user()->hasRole(['admin', 'super_admin'])) {
            $invoice->where('user_id', auth()->id());
        }
        
        $invoice = $invoice->where('id', $invoiceId)->firstOrFail();

        // Query para obter resumo por categoria
        $summary = DB::table('transactions')
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->select(
                'categories.id',
                'categories.name',
                'categories.icon',
                'categories.color',
                DB::raw('SUM(transactions.amount) as total'),
                DB::raw('COUNT(transactions.id) as count'),
                DB::raw('SUM(transactions.points_earned) as points')
            )
            ->where('transactions.invoice_id', $invoiceId)
            ->groupBy('categories.id', 'categories.name', 'categories.icon', 'categories.color')
            ->get();

        // Formatar dados para "Sem categoria" quando category_id é null
        foreach ($summary as $index => $item) {
            if ($item->id === null) {
                $summary[$index]->name = 'Sem categoria';
                $summary[$index]->icon = 'help-circle';
                $summary[$index]->color = 'gray';
            }
        }

        return $summary->toArray();
    }

    public function create(array $data): Invoice
    {
        // Ensure the user owns the card
        $card = Card::where([
            'id' => $data['card_id'],
            'user_id' => auth()->id()
        ])->firstOrFail();

        // Add user_id to the invoice data
        $data['user_id'] = auth()->id();

        // Extract transactions from the data if exists
        $transactions = $data['transactions'] ?? [];
        unset($data['transactions']);

        // Create the invoice
        $invoice = Invoice::create($data);

        // Create transactions if provided
        if (count($transactions) > 0) {
            foreach ($transactions as $transaction) {
                $transaction['invoice_id'] = $invoice->id;
                $this->transactionsService->create($transaction);
            }
        }

        return $invoice->load('transactions');
    }

    public function update(string $invoiceId, array $data): void
    {
        $invoice = Invoice::where([
            'id' => $invoiceId,
            'user_id' => auth()->id()
        ])->firstOrFail();

        $invoice->update($data);
    }

    public function destroy(string $invoiceId): void
    {
        $invoice = Invoice::where([
            'id' => $invoiceId,
            'user_id' => auth()->id()
        ])->firstOrFail();

        // Delete the file if exists
        if ($invoice->file_path) {
            Storage::delete($invoice->file_path);
        }

        $invoice->delete();
    }

    public function uploadInvoice(UploadedFile $file, string $cardId): array
    {
        $user = auth()->user();

        $card = Card::where([
            'id' => $cardId,
            'user_id' => $user->id
        ])->firstOrFail();

        $user = auth()->user();

        $path = $user->storeFile($file, 'invoices');

        $invoice = Invoice::create([
            'user_id' => $user->id,
            'card_id' => $cardId,
            'reference_date' => now(),
            'total_amount' => 0,
            'status' => 'Processando',
            'file_path' => $path,
        ]);

        dispatch(new ProcessInvoiceJob($invoice->id, $path));

        return [
            'invoice_id' => $invoice->id,
            'status' => 'processing'
        ];
    }

}