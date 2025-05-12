<?php

namespace Domains\Users\Actions\Users;

use Domains\Users\Models\User;
use Domains\Shared\Helpers\ListDataHelper;
use Illuminate\Pagination\LengthAwarePaginator;

class ListUsers
{
    public static function execute(array $filters): LengthAwarePaginator
    {
        $helper = new ListDataHelper(new User);

        $users = $helper->list($filters);

        return $users;
    }
}
