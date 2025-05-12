<?php

namespace Domains\Shared\Models;

use Domains\Shared\Traits\FiltersNullValues;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use FiltersNullValues, HasFactory, HasUlids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'street',
        'number',
        'complement',
        'district',
        'city',
        'state',
        'country',
        'postal_code',
        'reference',
        'addressable_id',
        'addressable_type',
    ];

    /**
     * Define o relacionamento polimórfico.
     */
    public function addressable()
    {
        return $this->morphTo();
    }
}
