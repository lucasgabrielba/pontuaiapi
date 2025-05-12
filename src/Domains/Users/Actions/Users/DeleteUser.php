<?php

namespace Domains\Users\Actions\Users;

use Domains\Users\Models\User;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DeleteUser
{
    public static function execute(string $organizationId, string $userId): void
    {
        try {
            $user = User::findOrFail($userId);

            self::authorize($user);

            $user->delete();
            
        } catch (ModelNotFoundException $e) {
            throw new Exception('Usuário não encontrado');
        } catch (Exception $e) {
            throw new Exception('Ocorreu um erro ao tentar deletar usuário: '.$e->getMessage());
        }
    }

    private static function authorize(User $user): void
    {
        $isDeletingMe = $user->id === auth()->id();

        if ($isDeletingMe) {
            throw new Exception('Você não pode deletar a si mesmo');
        }

        $isSuperAdmin = auth()->user()->hasRole('super_admin');

        if (!$isSuperAdmin) {
            throw new Exception('Apenas o super admin pode deletar usuários');
        }
    }
}
