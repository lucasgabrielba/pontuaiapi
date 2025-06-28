<?php

namespace Domains\Finance\Models;

use Domains\Finance\Models\Category;
use Domains\Finance\Models\Invoice;
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
        'category_id',
        'type',
        'title',
        'description',
        'recommendation',
        'impact_description',
        'potential_points_increase',
        'priority',
        'is_personalized',
        'applies_to_future',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_personalized' => 'boolean',
        'applies_to_future' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $with = ['category', 'createdBy'];

    /**
     * Get the invoice that owns the suggestion.
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the category associated with the suggestion.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the user who created the suggestion.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}