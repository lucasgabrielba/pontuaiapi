<?php

namespace Domains\Users\Models;

use Domains\Cards\Models\Card;
use Domains\Finance\Models\Invoice;
use Domains\Rewards\Models\Point;
use Domains\Users\Enums\UserStatus;
use Domains\Shared\Traits\FiltersNullValues;
use Domains\Users\Traits\HasFiles;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use FiltersNullValues,
        HasApiTokens,
        HasFactory,
        HasRoles,
        HasUlids,
        Notifiable,
        HasFiles,
        SoftDeletes;

    protected $guard_name = 'api';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'preferences'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'status' => UserStatus::class,
        'preferences' => 'array',
    ];


    public function cards()
    {
        return $this->hasMany(Card::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function points()
    {
        return $this->hasMany(Point::class);
    }

}
