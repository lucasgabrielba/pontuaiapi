<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Domains\Finance\Services\TransactionsService;
use Illuminate\Http\Request;

class TransactionsController extends Controller
{
    protected TransactionsService $transactionsService;

    public function __construct(TransactionsService $transactionsService)
    {
        $this->transactionsService = $transactionsService;
    }

    /**
     * Display a listing of the transactions.
     */
    public function index(Request $request)
    {
        $filters = $request->all();
        $transactions = $this->transactionsService->list($filters);

        return response()->json($transactions);
    }

    /**
     * Display the specified transaction.
     */
    public function show(string $transactionId)
    {
        $transaction = $this->transactionsService->get($transactionId);

        return response()->json($transaction);
    }

    /**
     * Update the specified transaction in storage.
     */
    public function update(Request $request, string $transactionId)
    {
        $data = $request->all();
        $this->transactionsService->update($transactionId, $data);

        return response()->json([
            'message' => 'Transação atualizada com sucesso',
        ]);
    }

    /**
     * Remove the specified transaction from storage.
     */
    public function destroy(string $transactionId)
    {
        $this->transactionsService->destroy($transactionId);

        return response()->json([
            'message' => 'Transação deletada com sucesso',
        ], 204);
    }

    /**
     * Get suggestions for better card/reward program based on this merchant.
     */
    public function suggestions(Request $request)
    {
        $merchantName = $request->input('merchant_name');
        $suggestions = $this->transactionsService->getSuggestions($merchantName);

        return response()->json($suggestions);
    }

    /**
     * Categorize a transaction.
     */
    public function categorize(Request $request, string $transactionId)
    {
        $categoryId = $request->input('category_id');
        $this->transactionsService->update($transactionId, ['category_id' => $categoryId]);

        return response()->json([
            'message' => 'Transação categorizada com sucesso',
        ]);
    }
}