<?php

namespace Domains\Finance\Models;

use Domains\Rewards\Models\Point;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
  use HasFactory, HasUlids;

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
    'is_recommended' => 'boolean',
  ];

  public function invoice()
  {
    return $this->belongsTo(Invoice::class);
  }

  public function category()
  {
    return $this->belongsTo(Category::class);
  }

  public function points()
  {
    return $this->hasMany(Point::class);
  }
}