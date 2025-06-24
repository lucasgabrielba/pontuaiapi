<?php

namespace Domains\Users\Actions\Users;

use Domains\Users\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class UpdateUser
{
    public static function execute(string $userId, array $data)
    {
        try {
            $user = User::where('id', $userId)->update($data);
            return $user;
        } catch (ModelNotFoundException $e) {
            throw new Exception('Usuário não encontrado');
        } catch (Exception $e) {
            throw new Exception('Ocorreu um erro ao tentar atualizar usuário: '.$e->getMessage());
        }
    }
}
