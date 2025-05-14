<?php

namespace Domains\Cards\Models;

use Domains\Rewards\Models\Point;
use Domains\Shared\Traits\FiltersNullValues;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RewardProgram extends Model
{
  use FiltersNullValues, HasFactory, HasUlids, SoftDeletes;

  protected $fillable = [
    'name',
    'code',
    'description',
    'website',
    'logo_path',
  ];

  /**
   * Get the cards for the reward program.
   */
  public function cards()
  {
    return $this->belongsToMany(Card::class)
      ->withPivot('conversion_rate', 'is_primary', 'terms')
      ->withTimestamps();
  }

  /**
   * Get the points for the reward program.
   */
  public function points()
  {
    return $this->hasMany(Point::class);
  }
}