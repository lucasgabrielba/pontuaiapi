<?php

namespace Domains\Finance\Models;

use Domains\Shared\Traits\FiltersNullValues;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use FiltersNullValues, HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'logo_url',
        'primary_color',
        'secondary_color',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}