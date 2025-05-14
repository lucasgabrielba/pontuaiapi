<?php

namespace Domains\Cards\Models;

use Domains\Rewards\Models\Point;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewardProgram extends Model
{
  use HasFactory, HasUlids;

  protected $fillable = [
    'name',
    'code',
    'description',
    'website',
    'logo_path',
  ];

  public function cards()
  {
    return $this->belongsToMany(Card::class)
      ->withPivot('conversion_rate', 'is_primary')
      ->withTimestamps();
  }

  public function points()
  {
    return $this->hasMany(Point::class);
  }
}