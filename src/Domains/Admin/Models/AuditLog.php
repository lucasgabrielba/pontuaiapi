<?php

namespace Domains\Admin\Models;

use Domains\Shared\Traits\FiltersNullValues;
use Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use FiltersNullValues, HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'action_type',
        'description',
        'ip_address',
        'user_agent',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    /**
     * Get the user that performed the action
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log an admin action
     */
    public static function logAction(string $actionType, string $description, array $metadata = []): void
    {
        static::create([
            'user_id' => auth()->id(),
            'action_type' => $actionType,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata
        ]);
    }
}
