<?php

namespace Domains\Finance\Services;

use Domains\Finance\Models\Suggestion;
use Illuminate\Support\Facades\DB;

class SuggestionsService
{
    /**
     * Lista sugestões com filtros opcionais
     */
    public function list(array $filters = [])
    {
        $query = Suggestion::query()->with('createdBy:id,name');

        // Aplicar filtros
        if (!empty($filters['invoice_id'])) {
            $query->where('invoice_id', $filters['invoice_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['is_personalized'])) {
            $query->where('is_personalized', $filters['is_personalized']);
        }

        if (!empty($filters['applies_to_future'])) {
            $query->where('applies_to_future', $filters['applies_to_future']);
        }

        // Ordenação
        $query->orderBy('created_at', 'desc');

        // Paginação
        if (!empty($filters['page']) || !empty($filters['per_page'])) {
            return $query->paginate($filters['per_page'] ?? 15);
        }

        return $query->get();
    }

    /**
     * Busca uma sugestão específica
     */
    public function findById(string $id)
    {
        return Suggestion::with('createdBy:id,name')->findOrFail($id);
    }

    /**
     * Cria uma nova sugestão
     */
    public function create(array $data)
    {
        return Suggestion::create([...$data, 'created_by' => auth()->user()->id]);
    }

    /**
     * Atualiza uma sugestão
     */
    public function update(string $id, array $data)
    {
        $suggestion = Suggestion::findOrFail($id);
        $suggestion->update($data);
        return $suggestion;
    }

    /**
     * Deleta uma sugestão
     */
    public function delete(string $id)
    {
        $suggestion = Suggestion::findOrFail($id);
        return $suggestion->delete();
    }

    /**
     * Obtém estatísticas das sugestões de uma fatura
     */
    public function getStatsByInvoice(string $invoiceId)
    {
        // Query principal para contagens usando aspas simples para PostgreSQL
        $stats = DB::table('suggestions')
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority,
                COUNT(CASE WHEN priority = 'medium' THEN 1 END) as medium_priority,
                COUNT(CASE WHEN priority = 'low' THEN 1 END) as low_priority,
                COUNT(CASE WHEN is_personalized = true THEN 1 END) as personalized,
                COUNT(CASE WHEN applies_to_future = true THEN 1 END) as applies_to_future
            ")
            ->where('invoice_id', $invoiceId)
            ->first();

        // Query para contagem por tipo
        $byType = DB::table('suggestions')
            ->select('type', DB::raw('COUNT(*) as count'))
            ->where('invoice_id', $invoiceId)
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return [
            'total' => (int) $stats->total,
            'by_priority' => [
                'high' => (int) $stats->high_priority,
                'medium' => (int) $stats->medium_priority,
                'low' => (int) $stats->low_priority,
            ],
            'personalized' => (int) $stats->personalized,
            'applies_to_future' => (int) $stats->applies_to_future,
            'by_type' => $byType,
        ];
    }

    /**
     * Lista sugestões de uma fatura específica
     */
    public function getByInvoice(string $invoiceId)
    {
        return $this->list(['invoice_id' => $invoiceId]);
    }

    /**
     * Aplica sugestões a faturas futuras
     */
    public function applyToFutureInvoices(string $userId)
    {
        // Buscar sugestões que se aplicam ao futuro
        $suggestions = Suggestion::where('applies_to_future', true)
            ->whereHas('invoice', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->get();

        // Aqui você pode implementar a lógica para aplicar essas sugestões
        // Por exemplo, criar regras automáticas ou notificações
        
        return $suggestions;
    }

    /**
     * Busca sugestões similares para evitar duplicatas
     */
    public function findSimilar(string $invoiceId, string $type, string $title)
    {
        return Suggestion::where('invoice_id', $invoiceId)
            ->where('type', $type)
            ->where('title', 'ILIKE', '%' . $title . '%')
            ->exists();
    }

    /**
     * Marca sugestões como lidas/visualizadas
     */
    public function markAsViewed(array $suggestionIds, string $userId)
    {
        // Implementar lógica de visualização se necessário
        // Por exemplo, criar uma tabela de pivot suggestion_views
        
        return true;
    }

    /**
     * Obtém sugestões por prioridade
     */
    public function getByPriority(string $priority, string $invoiceId = null)
    {
        $query = Suggestion::where('priority', $priority)
            ->with('createdBy:id,name');

        if ($invoiceId) {
            $query->where('invoice_id', $invoiceId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Obtém estatísticas globais das sugestões
     */
    public function getGlobalStats(array $filters = [])
    {
        $query = DB::table('suggestions');

        // Aplicar filtros de data se fornecidos
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $stats = $query->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority,
                COUNT(CASE WHEN priority = 'medium' THEN 1 END) as medium_priority,
                COUNT(CASE WHEN priority = 'low' THEN 1 END) as low_priority,
                COUNT(CASE WHEN is_personalized = true THEN 1 END) as personalized,
                COUNT(CASE WHEN applies_to_future = true THEN 1 END) as applies_to_future
            ")
            ->first();

        // Estatísticas por tipo
        $byType = DB::table('suggestions')
            ->select('type', DB::raw('COUNT(*) as count'));
            
        if (!empty($filters['date_from'])) {
            $byType->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $byType->whereDate('created_at', '<=', $filters['date_to']);
        }

        $byType = $byType->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return [
            'total' => (int) $stats->total,
            'by_priority' => [
                'high' => (int) $stats->high_priority,
                'medium' => (int) $stats->medium_priority,
                'low' => (int) $stats->low_priority,
            ],
            'personalized' => (int) $stats->personalized,
            'applies_to_future' => (int) $stats->applies_to_future,
            'by_type' => $byType,
        ];
    }
}