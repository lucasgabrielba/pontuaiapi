<?php

namespace Domains\Admin\Services;

use Domains\Finance\Models\Invoice;
use Domains\Admin\Models\InvoiceSuggestion;
use Domains\Finance\Jobs\ProcessInvoiceJob;
use Domains\Shared\Helpers\ListDataHelper;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class AdminInvoicesService
{
  /**
   * List all invoices (admin view - no user filtering).
   */
  public function listAllInvoices(array $filters): LengthAwarePaginator
  {
    $helper = new ListDataHelper(new Invoice);

    if (!isset($filters['order'])) {
      $filters['order'] = '-created_at';
    }

    // Include user and card information
    if (!isset($filters['include'])) {
      $filters['include'] = 'user,card';
    }

    return $helper->list($filters);
  }

  /**
   * Get a specific invoice (admin view - no user filtering).
   */
  public function getInvoice(string $invoiceId): Invoice
  {
    return Invoice::with(['user', 'card', 'transactions'])->findOrFail($invoiceId);
  }

  /**
   * Get suggestions for a specific invoice.
   */
  public function getInvoiceSuggestions(string $invoiceId): array
  {
    $suggestions = InvoiceSuggestion::where('invoice_id', $invoiceId)
      ->with('createdBy')
      ->orderBy('created_at', 'desc')
      ->get();

    return $suggestions->toArray();
  }

  /**
   * Create a new suggestion for an invoice.
   */
  public function createSuggestion(string $invoiceId, array $data): InvoiceSuggestion
  {
    // Verify invoice exists
    $invoice = Invoice::findOrFail($invoiceId);

    $suggestionData = [
      'invoice_id' => $invoiceId,
      'title' => $data['title'],
      'description' => $data['description'],
      'type' => $data['type'] ?? 'general',
      'priority' => $data['priority'] ?? 'medium',
      'status' => 'pending',
      'created_by' => auth()->id(),
      'additional_data' => $data['additional_data'] ?? null,
    ];

    return InvoiceSuggestion::create($suggestionData);
  }

  /**
   * Update an existing suggestion.
   */
  public function updateSuggestion(string $suggestionId, array $data): void
  {
    $suggestion = InvoiceSuggestion::findOrFail($suggestionId);

    $updateData = array_filter([
      'title' => $data['title'] ?? null,
      'description' => $data['description'] ?? null,
      'type' => $data['type'] ?? null,
      'priority' => $data['priority'] ?? null,
      'status' => $data['status'] ?? null,
      'additional_data' => $data['additional_data'] ?? null,
      'updated_by' => auth()->id(),
    ]);

    $suggestion->update($updateData);
  }

  /**
   * Delete a suggestion.
   */
  public function deleteSuggestion(string $suggestionId): void
  {
    $suggestion = InvoiceSuggestion::findOrFail($suggestionId);
    $suggestion->delete();
  }

  /**
   * Reprocess an invoice.
   */
  public function reprocessInvoice(string $invoiceId): array
  {
    $invoice = Invoice::findOrFail($invoiceId);

    // Only reprocess if there's a file
    if (!$invoice->file_path) {
      throw new \Exception('Fatura não possui arquivo para reprocessamento');
    }

    // Delete existing transactions
    $invoice->transactions()->delete();

    // Reset invoice status
    $invoice->update([
      'status' => 'Processando',
      'total_amount' => 0,
    ]);

    // Dispatch reprocessing job
    dispatch(new ProcessInvoiceJob($invoice->id, $invoice->file_path));

    Log::info('Fatura reprocessada pelo admin', [
      'invoice_id' => $invoiceId,
      'admin_id' => auth()->id()
    ]);

    return [
      'invoice_id' => $invoiceId,
      'status' => 'processing',
      'message' => 'Fatura enviada para reprocessamento'
    ];
  }

  /**
   * Export invoice data.
   */
  public function exportInvoiceData(string $invoiceId): array
  {
    $invoice = Invoice::with(['user', 'card', 'transactions.category'])
      ->findOrFail($invoiceId);

    $exportData = [
      'invoice' => [
        'id' => $invoice->id,
        'reference_date' => $invoice->reference_date,
        'total_amount' => $invoice->total_amount / 100, // Convert to reais
        'status' => $invoice->status,
        'created_at' => $invoice->created_at,
        'updated_at' => $invoice->updated_at,
      ],
      'user' => [
        'id' => $invoice->user->id,
        'name' => $invoice->user->name,
        'email' => $invoice->user->email,
      ],
      'card' => [
        'id' => $invoice->card->id,
        'name' => $invoice->card->name,
        'bank' => $invoice->card->bank,
        'last_digits' => $invoice->card->last_digits,
      ],
      'transactions' => $invoice->transactions->map(function ($transaction) {
        return [
          'id' => $transaction->id,
          'merchant_name' => $transaction->merchant_name,
          'transaction_date' => $transaction->transaction_date,
          'amount' => $transaction->amount / 100, // Convert to reais
          'points_earned' => $transaction->points_earned,
          'category' => $transaction->category ? [
            'id' => $transaction->category->id,
            'name' => $transaction->category->name,
            'code' => $transaction->category->code,
          ] : null,
          'description' => $transaction->description,
        ];
      })->toArray(),
      'summary' => [
        'total_transactions' => $invoice->transactions->count(),
        'total_points' => $invoice->transactions->sum('points_earned'),
        'categories_count' => $invoice->transactions->whereNotNull('category_id')->unique('category_id')->count(),
      ],
      'export_timestamp' => now()->toISOString(),
      'exported_by' => auth()->user()->name,
    ];

    return $exportData;
  }

  /**
   * Get processing logs for an invoice.
   */
  public function getInvoiceLogs(string $invoiceId): array
  {
    $invoice = Invoice::findOrFail($invoiceId);

    // This would typically fetch from a logs table or log files
    // For now, return mock data
    return [
      [
        'timestamp' => $invoice->created_at,
        'level' => 'info',
        'message' => 'Fatura criada',
        'context' => [
          'user_id' => $invoice->user_id,
          'card_id' => $invoice->card_id,
        ]
      ],
      [
        'timestamp' => $invoice->updated_at,
        'level' => 'info',
        'message' => 'Status atualizado para: ' . $invoice->status,
        'context' => [
          'status' => $invoice->status,
        ]
      ],
    ];
  }

  /**
   * Mark invoice as problematic.
   */
  public function markAsProblematic(string $invoiceId, string $reason): void
  {
    $invoice = Invoice::findOrFail($invoiceId);

    $invoice->update([
      'status' => 'Erro',
      'notes' => ($invoice->notes ? $invoice->notes . "\n\n" : '') .
        "Marcado como problemático em " . now()->format('d/m/Y H:i') .
        " por " . auth()->user()->name . ": " . $reason
    ]);

    Log::warning('Fatura marcada como problemática pelo admin', [
      'invoice_id' => $invoiceId,
      'reason' => $reason,
      'admin_id' => auth()->id()
    ]);
  }
}