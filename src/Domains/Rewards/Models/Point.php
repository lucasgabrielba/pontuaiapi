<?php

namespace Domains\Rewards\Models;

use Domains\Cards\Models\RewardProgram;
use Domains\Finance\Models\Transaction;
use Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Point extends Model
{
  use HasFactory, HasUlids;

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
    'expiration_date' => 'date',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function rewardProgram()
  {
    return $this->belongsTo(RewardProgram::class);
  }

  public function transaction()
  {
    return $this->belongsTo(Transaction::class);
  }
}