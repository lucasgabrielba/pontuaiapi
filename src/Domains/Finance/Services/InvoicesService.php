<?php

namespace Domains\Finance\Services;

use Domains\Cards\Models\Card;
use Domains\Finance\Jobs\ProcessInvoiceJob;
use Domains\Finance\Models\Invoice;
use Domains\Finance\Models\Transaction;
use Domains\Shared\Helpers\ListDataHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvoicesService
{
    protected TransactionsService $transactionsService;

    public function __construct(TransactionsService $transactionsService)
    {
        $this->transactionsService = $transactionsService;
    }

    /**
     * List all invoices with filtering.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $helper = new ListDataHelper(new Invoice);
        
        // Ensure only user's invoices are returned
        $filters['user_id'] = auth()->id();
        
        return $helper->list($filters);
    }

    /**
     * Get a specific invoice.
     */
    public function get(string $invoiceId): Invoice
    {
        $invoice = Invoice::where([
            'id' => $invoiceId,
            'user_id' => auth()->id()
        ])->firstOrFail();

        return $invoice->load('transactions');
    }

    /**
     * Create a new invoice with transactions.
     */
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

    /**
     * Update an existing invoice.
     */
    public function update(string $invoiceId, array $data): void
    {
        $invoice = Invoice::where([
            'id' => $invoiceId,
            'user_id' => auth()->id()
        ])->firstOrFail();
        
        $invoice->update($data);
    }

    /**
     * Delete an invoice.
     */
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

    /**
     * Upload and process an invoice file.
     */
    public function uploadInvoice(UploadedFile $file, string $cardId): array
    {
        // Verify if card belongs to the authenticated user
        $card = Card::where([
            'id' => $cardId,
            'user_id' => auth()->id()
        ])->firstOrFail();
        
        // Store the file
        $path = $this->storeFile($file);
        
        // Create a new invoice in "processing" state
        $invoice = Invoice::create([
            'user_id' => auth()->id(),
            'card_id' => $cardId,
            'reference_date' => now(),
            'total_amount' => 0, // Will be updated after processing
            'status' => 'Processando',
            'file_path' => $path,
        ]);
        
        // Queue the processing job
        dispatch(new ProcessInvoiceJob($invoice->id, $path));
        
        return [
            'invoice_id' => $invoice->id,
            'status' => 'processing'
        ];
    }

    /**
     * Store the uploaded file.
     */
    private function storeFile(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $fileName = Str::ulid() . '.' . $extension;
        $path = $file->storeAs('invoices', $fileName, 's3');
        
        return $path;
    }

    /**
     * Get transactions for an invoice.
     */
    public function getTransactions(string $invoiceId): array
    {
        $invoice = Invoice::where([
            'id' => $invoiceId,
            'user_id' => auth()->id()
        ])->firstOrFail();
        
        return $invoice->transactions()->get()->toArray();
    }

    /**
     * Temporary method to simulate invoice processing.
     * This would be replaced by actual AI processing.
     */
    private function simulateProcessing(Invoice $invoice, UploadedFile $file): void
    {
        $extension = $file->getClientOriginalExtension();
        
        // Very basic simulation based on file type
        if ($extension === 'csv') {
            $this->processCsvFile($invoice, $file);
        } else {
            // For PDF/images, we'd use OCR and AI in a real implementation
            // For now, just create some dummy data
            $this->createDummyTransactions($invoice);
        }
        
        // Update invoice status and total
        $totalAmount = $invoice->transactions()->sum('amount');
        
        $invoice->update([
            'status' => 'Pendente',
            'total_amount' => $totalAmount,
            'due_date' => now()->addDays(15),
            'closing_date' => now()->subDays(5),
        ]);
    }

    /**
     * Process a CSV file to extract transactions.
     */
    private function processCsvFile(Invoice $invoice, UploadedFile $file): void
    {
        $path = $file->getRealPath();
        $rows = array_map('str_getcsv', file($path));
        
        // Assume first row is header
        $header = array_shift($rows);
        
        $totalAmount = 0;
        
        foreach ($rows as $row) {
            if (count($row) >= 3) { // Basic validation
                $transaction = [
                    'invoice_id' => $invoice->id,
                    'merchant_name' => $row[0],
                    'transaction_date' => date('Y-m-d', strtotime($row[1])),
                    'amount' => (int)($row[2] * 100), // Convert to cents
                    'description' => $row[3] ?? null,
                ];
                
                Transaction::create($transaction);
                $totalAmount += $transaction['amount'];
            }
        }
    }

    /**
     * Create dummy transactions for testing.
     */
    private function createDummyTransactions(Invoice $invoice): void
    {
        $merchants = [
            'Supermercado Extra',
            'Netflix',
            'Amazon',
            'Posto Ipiranga',
            'Restaurante Outback',
            'Farmácia Droga Raia',
            'Uber',
            'iFood'
        ];
        
        $totalAmount = 0;
        
        // Create 5-10 random transactions
        $count = rand(5, 10);
        for ($i = 0; $i < $count; $i++) {
            $amount = rand(1000, 50000); // 10-500 reais em centavos
            $transaction = [
                'invoice_id' => $invoice->id,
                'merchant_name' => $merchants[array_rand($merchants)],
                'transaction_date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
                'amount' => $amount,
                'description' => 'Transação gerada automaticamente',
            ];
            
            Transaction::create($transaction);
            $totalAmount += $amount;
        }
    }
}