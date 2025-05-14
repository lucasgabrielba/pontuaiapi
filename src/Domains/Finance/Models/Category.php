<?php

namespace Domains\Finance\Models;

use Domains\Shared\Traits\FiltersNullValues;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use FiltersNullValues, HasFactory, HasUlids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'code',
        'description',
        'icon',
        'color',
    ];

    /**
     * Get the transactions for the category.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}