<?php

namespace Domains\Admin\Services;

use Domains\Finance\Jobs\ProcessInvoiceJob;
use Domains\Finance\Models\Invoice;
use Domains\Finance\Models\Transaction;
use Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminInvoicesService
{
    /**
     * Obter lista de usuários com informações de faturas
     */
    public function getUsers(array $filters): array
    {
        $query = User::select([
            'id',
            'name', 
            'email',
            'created_at',
            'status'
        ])
        ->withCount('invoices')
        ->with(['invoices' => function($query) {
            $query->latest()->take(1)->select('user_id', 'created_at');
        }]);

        // Filtros
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['has_invoices'])) {
            if ($filters['has_invoices']) {
                $query->has('invoices');
            } else {
                $query->doesntHave('invoices');
            }
        }

        // Ordenação
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginação
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        // Processar dados
        $users = $paginated->getCollection()->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'invoices_count' => $user->invoices_count,
                'last_invoice_date' => $user->invoices->first()?->created_at?->toISOString(),
                'created_at' => $user->created_at->toISOString(),
                'status' => $user->status
            ];
        });

        return [
            'current_page' => $paginated->currentPage(),
            'data' => $users,
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total()
        ];
    }

    /**
     * Obter faturas de um usuário específico
     */
    public function getUserInvoices(string $userId, array $filters): array
    {
        $query = Invoice::where('user_id', $userId)
            ->with(['card:id,name,last_digits'])
            ->select([
                'id',
                'card_id',
                'reference_date',
                'total_amount',
                'status',
                'created_at'
            ])
            ->withCount('transactions');

        // Filtros
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('card', function($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%");
            });
        }

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('reference_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('reference_date', '<=', $filters['date_to']);
        }

        // Ordenação
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginação
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        // Processar dados
        $invoices = $paginated->getCollection()->map(function($invoice) {
            return [
                'id' => $invoice->id,
                'card_name' => $invoice->card->name,
                'card_last_digits' => $invoice->card->last_digits,
                'reference_date' => $invoice->reference_date->toDateString(),
                'status' => $invoice->status,
                'total_amount' => $invoice->total_amount,
                'created_at' => $invoice->created_at->toISOString(),
                'transactions_count' => $invoice->transactions_count
            ];
        });

        return [
            'current_page' => $paginated->currentPage(),
            'data' => $invoices,
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total()
        ];
    }

    /**
     * Obter detalhes de uma fatura específica
     */
    public function getInvoiceDetails(string $invoiceId): array
    {
        $invoice = Invoice::with(['card', 'user'])
            ->findOrFail($invoiceId);

        return [
            'id' => $invoice->id,
            'reference_date' => $invoice->reference_date->toDateString(),
            'total_amount' => $invoice->total_amount,
            'status' => $invoice->status,
            'due_date' => $invoice->due_date?->toDateString(),
            'closing_date' => $invoice->closing_date?->toDateString(),
            'notes' => $invoice->notes,
            'created_at' => $invoice->created_at->toISOString(),
            'updated_at' => $invoice->updated_at->toISOString(),
            'card' => [
                'id' => $invoice->card->id,
                'name' => $invoice->card->name,
                'bank' => $invoice->card->bank,
                'last_digits' => $invoice->card->last_digits
            ],
            'user' => [
                'id' => $invoice->user->id,
                'name' => $invoice->user->name,
                'email' => $invoice->user->email
            ]
        ];
    }

    /**
     * Obter transações de uma fatura específica
     */
    public function getInvoiceTransactions(string $invoiceId, array $filters): array
    {
        $query = Transaction::where('invoice_id', $invoiceId)
            ->with(['category:id,name,icon,color']);

        // Filtros
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where('merchant_name', 'ILIKE', "%{$search}%");
        }

        if (isset($filters['category_filter']) && $filters['category_filter'] !== 'all') {
            if ($filters['category_filter'] === 'uncategorized') {
                $query->whereNull('category_id');
            } else {
                $query->where('category_id', $filters['category_filter']);
            }
        }

        // Ordenação
        $sortField = $filters['sort_field'] ?? 'transaction_date';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        // Paginação
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return $paginated->toArray();
    }

    /**
     * Obter resumo por categoria de uma fatura
     */
    public function getInvoiceCategorySummary(string $invoiceId): array
    {
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

        // Processar dados para "Sem categoria"
        return $summary->map(function($item) {
            if ($item->id === null) {
                $item->name = 'Sem categoria';
                $item->icon = 'help-circle';
                $item->color = 'gray';
            }
            return $item;
        })->toArray();
    }

    /**
     * Reprocessar uma fatura com erro
     */
    public function reprocessInvoice(string $invoiceId): array
    {
        $invoice = Invoice::findOrFail($invoiceId);

        if ($invoice->status !== 'Erro') {
            throw new \Exception('Apenas faturas com erro podem ser reprocessadas');
        }

        if (!$invoice->file_path) {
            throw new \Exception('Arquivo da fatura não encontrado');
        }

        // Atualizar status para processando
        $invoice->update(['status' => 'Processando']);

        // Reprocessar
        dispatch(new ProcessInvoiceJob($invoice->id, $invoice->file_path));

        Log::info('Fatura reprocessada', [
            'invoice_id' => $invoiceId,
            'admin_user_id' => auth()->id()
        ]);

        return [
            'message' => 'Fatura enviada para reprocessamento',
            'status' => 'Processando'
        ];
    }

    /**
     * Atualizar status de uma fatura manualmente
     */
    public function updateInvoiceStatus(string $invoiceId, string $status): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        
        $invoice->update(['status' => $status]);

        Log::info('Status da fatura atualizado manualmente', [
            'invoice_id' => $invoiceId,
            'old_status' => $invoice->getOriginal('status'),
            'new_status' => $status,
            'admin_user_id' => auth()->id()
        ]);
    }

    /**
     * Obter estatísticas gerais de faturas
     */
    public function getInvoicesStats(): array
    {
        $totalInvoices = Invoice::count();
        $totalUsers = User::has('invoices')->count();
        $totalAmount = Invoice::sum('total_amount');
        
        $statusStats = Invoice::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $recentInvoices = Invoice::where('created_at', '>=', now()->subDays(30))->count();

        return [
            'total_invoices' => $totalInvoices,
            'total_users' => $totalUsers,
            'total_amount' => $totalAmount,
            'status_breakdown' => $statusStats,
            'recent_invoices' => $recentInvoices,
            'average_amount' => $totalInvoices > 0 ? round($totalAmount / $totalInvoices) : 0
        ];
    }

    /**
     * Deletar fatura (apenas admin)
     */
    public function deleteInvoice(string $invoiceId): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        
        Log::info('Fatura deletada pelo admin', [
            'invoice_id' => $invoiceId,
            'user_id' => $invoice->user_id,
            'admin_user_id' => auth()->id()
        ]);

        $invoice->delete();
    }

    /**
     * Obter sugestões de uma fatura (mock data para demonstração)
     */
    public function getInvoiceSuggestions(string $invoiceId): array
    {
        // Em um cenário real, isso viria de uma tabela de sugestões
        return [
            [
                'id' => '1',
                'type' => 'card_recommendation',
                'title' => 'Recomendação de Cartão',
                'description' => 'Para maximizar pontos em supermercados',
                'recommendation' => 'Use o Cartão XYZ para gastos em supermercados',
                'priority' => 'high',
                'status' => 'active'
            ],
            [
                'id' => '2',
                'type' => 'merchant_recommendation',
                'title' => 'Recomendação de Estabelecimento',
                'description' => 'Mude para estabelecimentos parceiros',
                'recommendation' => 'Supermercado ABC oferece 3x mais pontos',
                'priority' => 'medium',
                'status' => 'active'
            ]
        ];
    }

    /**
     * Criar nova sugestão para uma fatura (mock)
     */
    public function createSuggestion(string $invoiceId, array $data): array
    {
        // Em um cenário real, isso salvaria na tabela de sugestões
        Log::info('Sugestão criada', [
            'invoice_id' => $invoiceId,
            'suggestion_data' => $data,
            'admin_user_id' => auth()->id()
        ]);

        return array_merge($data, [
            'id' => uniqid(),
            'created_at' => now()->toISOString()
        ]);
    }

    /**
     * Atualizar uma sugestão existente (mock)
     */
    public function updateSuggestion(string $invoiceId, string $suggestionId, array $data): void
    {
        Log::info('Sugestão atualizada', [
            'invoice_id' => $invoiceId,
            'suggestion_id' => $suggestionId,
            'suggestion_data' => $data,
            'admin_user_id' => auth()->id()
        ]);
    }

    /**
     * Deletar uma sugestão (mock)
     */
    public function deleteSuggestion(string $invoiceId, string $suggestionId): void
    {
        Log::info('Sugestão deletada', [
            'invoice_id' => $invoiceId,
            'suggestion_id' => $suggestionId,
            'admin_user_id' => auth()->id()
        ]);
    }
}