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

    public function list(array $filters): LengthAwarePaginator
    {
        $helper = new ListDataHelper(new Invoice);

        // Ensure only user's invoices are returned
        $filters['user_id'] = auth()->id();

        return $helper->list($filters);
    }

    public function get(string $invoiceId): Invoice
    {
        $invoice = Invoice::where([
            'id' => $invoiceId,
            'user_id' => auth()->id()
        ])->firstOrFail();

        return $invoice->load('transactions');
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

    public function getTransactions(string $invoiceId): array
    {
        $invoice = Invoice::where([
            'id' => $invoiceId,
            'user_id' => auth()->id()
        ])->firstOrFail();

        return $invoice->transactions()->get()->toArray();
    }

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
                    'amount' => (int) ($row[2] * 100), // Convert to cents
                    'description' => $row[3] ?? null,
                ];

                Transaction::create($transaction);
                $totalAmount += $transaction['amount'];
            }
        }
    }

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