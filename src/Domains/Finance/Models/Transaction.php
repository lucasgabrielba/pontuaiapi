<?php

namespace Domains\Finance\Models;

use Domains\Rewards\Models\Point;
use Domains\Shared\Traits\FiltersNullValues;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use FiltersNullValues, HasFactory, HasUlids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'invoice_id',
        'category_id',
        'merchant_name',
        'transaction_date',
        'amount',
        'points_earned',
        'is_recommended',
        'description',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'integer',
        'points_earned' => 'integer',
        'is_recommended' => 'boolean',
    ];

    /**
     * Get the invoice that owns the transaction.
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the category for the transaction.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the points earned from this transaction.
     */
    public function points()
    {
        return $this->hasMany(Point::class);
    }
}