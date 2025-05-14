<?php

namespace Domains\Finance\Services;

use Domains\Finance\Models\Transaction;
use Domains\Shared\Helpers\ListDataHelper;
use Illuminate\Pagination\LengthAwarePaginator;

class TransactionsService
{
    /**
     * List all transactions with filtering.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $helper = new ListDataHelper(new Transaction);
        
        // Ensure only user's transactions are returned by joining with invoices
        $transactions = Transaction::join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->where('invoices.user_id', auth()->id())
            ->select('transactions.*');
            
        // Apply additional filters if needed
        if (isset($filters['merchant_name'])) {
            $transactions->where('merchant_name', 'like', '%' . $filters['merchant_name'] . '%');
        }
        
        if (isset($filters['category_id'])) {
            $transactions->where('category_id', $filters['category_id']);
        }
        
        if (isset($filters['date_from'])) {
            $transactions->where('transaction_date', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $transactions->where('transaction_date', '<=', $filters['date_to']);
        }
        
        return $transactions->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get a specific transaction.
     */
    public function get(string $transactionId): Transaction
    {
        return Transaction::whereHas('invoice', function ($query) {
            $query->where('user_id', auth()->id());
        })->findOrFail($transactionId);
    }

    /**
     * Create a new transaction.
     */
    public function create(array $data): Transaction
    {
        // Calculate points earned based on the amount and card conversion rate
        $data['points_earned'] = $this->calculatePointsEarned($data);
        
        return Transaction::create($data);
    }

    /**
     * Update an existing transaction.
     */
    public function update(string $transactionId, array $data): void
    {
        $transaction = Transaction::whereHas('invoice', function ($query) {
            $query->where('user_id', auth()->id());
        })->findOrFail($transactionId);
        
        // Recalculate points if amount changed
        if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
            $data['points_earned'] = $this->calculatePointsEarned($data);
        }
        
        $transaction->update($data);
        
        // Update invoice total amount if necessary
        if (isset($data['amount'])) {
            $this->updateInvoiceTotal($transaction->invoice_id);
        }
    }

    /**
     * Delete a transaction.
     */
    public function destroy(string $transactionId): void
    {
        $transaction = Transaction::whereHas('invoice', function ($query) {
            $query->where('user_id', auth()->id());
        })->findOrFail($transactionId);
        
        $invoiceId = $transaction->invoice_id;
        $transaction->delete();
        
        // Update invoice total
        $this->updateInvoiceTotal($invoiceId);
    }
    
    /**
     * Get transaction suggestions based on merchant and user history.
     */
    public function getSuggestions(string $merchantName): array
    {
        // This would analyze user spending patterns and recommend better cards/programs
        // For now, just return a simple suggestion
        return [
            'message' => 'Você poderia ganhar mais pontos usando outro cartão para compras em ' . $merchantName,
            'suggested_cards' => [], // Would contain actual recommendations
        ];
    }
    
    /**
     * Update the total amount of an invoice based on its transactions.
     */
    private function updateInvoiceTotal(string $invoiceId): void
    {
        $total = Transaction::where('invoice_id', $invoiceId)->sum('amount');
        
        // Update the invoice total
        \Domains\Finance\Models\Invoice::where('id', $invoiceId)->update([
            'total_amount' => $total
        ]);
    }
    
    /**
     * Calculate points earned for a transaction.
     * This is a simplified version - would be more complex in reality.
     */
    private function calculatePointsEarned(array $data): int
    {
        // In a real implementation, this would:
        // 1. Get the card's conversion rate from the database
        // 2. Check if the merchant has any special multipliers
        // 3. Apply any category bonuses
        // 4. Calculate the final points
        
        // For now, use a simple 1 point per real conversion
        return (int)($data['amount'] / 100);
    }
}