<?php

namespace Domains\Finance\Services;

use Domains\Finance\Models\Category;
use Domains\Shared\Helpers\ListDataHelper;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoriesService
{
    /**
     * List all categories with filtering.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $helper = new ListDataHelper(new Category);
        return $helper->list($filters);
    }

    /**
     * Get a specific category.
     */
    public function get(string $categoryId): Category
    {
        return Category::findOrFail($categoryId);
    }

    /**
     * Create a new category.
     */
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    /**
     * Update an existing category.
     */
    public function update(string $categoryId, array $data): void
    {
        $category = Category::findOrFail($categoryId);
        $category->update($data);
    }

    /**
     * Delete a category.
     */
    public function destroy(string $categoryId): void
    {
        Category::findOrFail($categoryId)->delete();
    }
    
    /**
     * Get transactions for a specific category.
     */
    public function getTransactions(string $categoryId, array $filters = []): array
    {
        $category = Category::findOrFail($categoryId);
        
        $query = $category->transactions()
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->where('invoices.user_id', auth()->id())
            ->select('transactions.*');
            
        // Filtros adicionais
        if (isset($filters['date_from'])) {
            $query->where('transaction_date', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('transaction_date', '<=', $filters['date_to']);
        }
        
        return $query->paginate($filters['per_page'] ?? 15)->toArray();
    }
    
    /**
     * Auto-categorize a transaction based on the merchant name.
     */
    public function suggestCategory(string $merchantName): ?string
    {
        // Aqui seria implementada uma lógica para sugerir categoria
        // baseada no nome do estabelecimento e histórico de transações
        // Por enquanto, retornamos null (sem sugestão)
        return null;
    }
}