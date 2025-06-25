<?php

namespace Domains\Admin\Models;

use Domains\Finance\Models\Invoice;
use Domains\Users\Models\User;
use Domains\Shared\Traits\FiltersNullValues;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceSuggestion extends Model
{
    use FiltersNullValues, HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'title',
        'description',
        'type',
        'priority',
        'status',
        'created_by',
        'updated_by',
        'additional_data',
    ];

    protected $casts = [
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
     * Get the user who last updated the suggestion.
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}