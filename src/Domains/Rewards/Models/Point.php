<?php

namespace Domains\Rewards\Models;

use Domains\Cards\Models\RewardProgram;
use Domains\Finance\Models\Transaction;
use Domains\Shared\Traits\FiltersNullValues;
use Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Point extends Model
{
    use FiltersNullValues, HasFactory, HasUlids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'reward_program_id',
        'transaction_id',
        'amount',
        'expiration_date',
        'status',
        'description',
    ];

    protected $casts = [
        'amount' => 'integer',
        'expiration_date' => 'date',
    ];

    /**
     * Get the user that owns the points.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reward program associated with the points.
     */
    public function rewardProgram()
    {
        return $this->belongsTo(RewardProgram::class);
    }

    /**
     * Get the transaction that generated the points.
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}