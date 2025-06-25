<?php

namespace Domains\Shared\Models;

use Domains\Shared\Traits\FiltersNullValues;
use Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use FiltersNullValues, HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'severity',
        'read',
        'action_url',
        'metadata'
    ];

    protected $casts = [
        'read' => 'boolean',
        'metadata' => 'array'
    ];

    /**
     * Get the user this notification belongs to
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        $this->update(['read' => true]);
    }

    /**
     * Create a new admin notification
     */
    public static function create(array $attributes = [])
    {
        $attributes['read'] = $attributes['read'] ?? false;
        return parent::create($attributes);
    }

    /**
     * Get unread notifications count
     */
    public static function getUnreadCount(?string $userId = null): int
    {
        $query = static::where('read', false);
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        return $query->count();
    }
}