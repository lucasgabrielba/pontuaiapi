<?php

namespace Domains\Finance\Services;

use Domains\Finance\Models\Bank;
use Domains\Shared\Helpers\ListDataHelper;
use Illuminate\Pagination\LengthAwarePaginator;

class BanksService
{
    public function list(array $filters): LengthAwarePaginator
    {
        $helper = new ListDataHelper(new Bank);
        return $helper->list($filters);
    }

    public function get(string $bankId): Bank
    {
        return Bank::findOrFail($bankId);
    }

    public function create(array $data): Bank
    {
        return Bank::create($data);
    }

    public function update(string $bankId, array $data): void
    {
        $bank = Bank::findOrFail($bankId);
        $bank->update($data);
    }

    public function destroy(string $bankId): void
    {
        Bank::findOrFail($bankId)->delete();
    }
}