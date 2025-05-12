<?php

use Domains\Users\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}', function (User $user, $userId) {
    return $user->id === $userId;
});
