<?php

namespace Domains\Cards\Models;

use Domains\Finance\Models\Invoice;
use Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
  use HasFactory, HasUlids;

  protected $fillable = [
    'user_id',
    'name',
    'bank',
    'last_digits',
    'conversion_rate',
    'annual_fee',
    'active',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function rewardPrograms()
  {
    return $this->belongsToMany(RewardProgram::class)
      ->withPivot('conversion_rate', 'is_primary')
      ->withTimestamps();
  }

  public function invoices()
  {
    return $this->hasMany(Invoice::class);
  }
}