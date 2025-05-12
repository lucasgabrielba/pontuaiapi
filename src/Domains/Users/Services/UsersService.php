<?php

namespace Domains\Users\Services;

use Domains\Users\Actions\Users\CreateUser;
use Domains\Users\Actions\Users\DeleteUser;
use Domains\Users\Actions\Users\ListUsers;
use Domains\Users\Actions\Users\UpdateUser;
use Domains\Users\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UsersService
{
    public function list(array $filters): LengthAwarePaginator
    {
        return ListUsers::execute($filters);
    }

    public function create(array $data): User
    {
        return CreateUser::execute($data);
    }

    public function get(string $userId): User
    {
        return User::findOrFail($userId);
    }

    public function update(string $userId, array $data): void
    {
        UpdateUser::execute($userId, $data);
    }

    public function destroy(string $userId): void
    {
        DeleteUser::execute($userId);
    }

    public function changePassword($userId, array $data): void
    {
        $user = User::findOrFail($userId);

        if (!Hash::check($data['currentPassword'], $user->password)) {
            throw new HttpException(400, 'Senha atual incorreta');
        }

        $user->update([
            'password' => bcrypt($data['newPassword']),
        ]);
    }
}
