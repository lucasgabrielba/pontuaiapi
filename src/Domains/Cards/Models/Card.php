<?php

namespace Domains\Cards\Models;

use Domains\Finance\Models\Invoice;
use Domains\Shared\Traits\FiltersNullValues;
use Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Card extends Model
{
  use FiltersNullValues, HasFactory, HasUlids, SoftDeletes;

  protected $fillable = [
    'user_id',
    'name',
    'bank',
    'last_digits',
    'conversion_rate',
    'annual_fee',
    'active',
  ];

  protected $casts = [
    'conversion_rate' => 'float',
    'annual_fee' => 'float',
    'active' => 'boolean',
  ];

  /**
   * Get the user that owns the card.
   */
  public function user()
  {
    return $this->belongsTo(User::class);
  }

  /**
   * Get the reward programs for the card.
   */
  public function rewardPrograms()
  {
    return $this->belongsToMany(RewardProgram::class)
      ->withPivot('conversion_rate', 'is_primary', 'terms')
      ->withTimestamps();
  }

  /**
   * Get the invoices for the card.
   */
  public function invoices()
  {
    return $this->hasMany(Invoice::class);
  }
}