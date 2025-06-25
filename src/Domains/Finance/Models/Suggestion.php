<?php

namespace Domains\Finance\Models;

use Domains\Shared\Traits\FiltersNullValues;
use Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Suggestion extends Model
{
    use FiltersNullValues, HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'created_by',
        'title',
        'description',
        'type',
        'priority',
        'recommendation',
        'impact_description',
        'potential_points_increase',
        'is_personalized',
        'applies_to_future',
        'additional_data',
    ];

    protected $casts = [
        'is_personalized' => 'boolean',
        'applies_to_future' => 'boolean',
        'additional_data' => 'array',
    ];

    /**
     * Get the invoice that owns the suggestion.
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the user who created the suggestion.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope para filtrar por tipo
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope para filtrar por prioridade
     */
    public function scopeOfPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope para sugestões personalizadas
     */
    public function scopePersonalized($query)
    {
        return $query->where('is_personalized', true);
    }

    /**
     * Scope para sugestões que se aplicam ao futuro
     */
    public function scopeAppliesToFuture($query)
    {
        return $query->where('applies_to_future', true);
    }

    /**
     * Accessor para formatar o tipo para exibição
     */
    public function getTypeDisplayAttribute(): string
    {
        $types = [
            'card_recommendation' => 'Recomendação de Cartão',
            'merchant_recommendation' => 'Recomendação de Estabelecimento',
            'category_optimization' => 'Otimização de Categoria',
            'points_strategy' => 'Estratégia de Pontos',
            'general_tip' => 'Dica Geral',
        ];

        return $types[$this->type] ?? $this->type;
    }

    /**
     * Accessor para formatar a prioridade para exibição
     */
    public function getPriorityDisplayAttribute(): string
    {
        $priorities = [
            'low' => 'Baixa',
            'medium' => 'Média',
            'high' => 'Alta',
        ];

        return $priorities[$this->priority] ?? $this->priority;
    }
}