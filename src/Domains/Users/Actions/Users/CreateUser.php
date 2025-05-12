<?php

namespace Domains\Users\Actions\Users;

use Domains\Users\Events\Users\UserCreated;
use Domains\Users\Models\User;
use InvalidArgumentException;

class CreateUser
{
    public static function execute(array $data, string $organizationId): User
    {
        self::authorize($data);

        $user = User::create([
            ...$data,
            'password' => bcrypt('000000')
        ]);

        $user->assignRole($data['role']);

        return $user;
    }

    private static function authorize(array $data): void
    {
        $isNotAuthorized = !auth()->user()->hasRole('super_admin');

        if ($isNotAuthorized) {
            throw new InvalidArgumentException('Apenas o super admin pode criar usuários');
        }
    }
}