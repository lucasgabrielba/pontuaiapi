<?php

namespace Domains\Finance\Models;

use Domains\Cards\Models\Card;
use Domains\Shared\Traits\FiltersNullValues;
use Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
  use FiltersNullValues, HasFactory, HasUlids, SoftDeletes;

  protected $fillable = [
    'user_id',
    'card_id',
    'reference_date',
    'total_amount',
    'status',
    'file_path',
    'due_date',
    'closing_date',
    'notes',
  ];

  protected $casts = [
    'reference_date' => 'date',
    'due_date' => 'date',
    'closing_date' => 'date',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function card()
  {
    return $this->belongsTo(Card::class);
  }

  public function transactions()
  {
    return $this->hasMany(Transaction::class);
  }
}