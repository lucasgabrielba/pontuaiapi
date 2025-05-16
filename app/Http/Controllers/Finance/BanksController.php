<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreBankRequest;
use App\Http\Requests\Finance\UpdateBankRequest;
use Domains\Finance\Services\BanksService;
use Illuminate\Http\Request;

class BanksController extends Controller
{
    protected BanksService $banksService;

    public function __construct(BanksService $banksService)
    {
        $this->banksService = $banksService;
    }

    public function index(Request $request)
    {
        $filters = $request->all();
        $banks = $this->banksService->list($filters);

        return response()->json($banks);
    }

    public function store(StoreBankRequest $request)
    {
        $data = $request->validated();
        $bank = $this->banksService->create($data);

        return response()->json($bank, 201);
    }

    public function show(string $bankId)
    {
        $bank = $this->banksService->get($bankId);

        return response()->json($bank);
    }

    public function update(UpdateBankRequest $request, string $bankId)
    {
        $data = $request->validated();
        $this->banksService->update($bankId, $data);

        return response()->json([
            'message' => 'Banco atualizado com sucesso',
        ]);
    }

    public function destroy(string $bankId)
    {
        $this->banksService->destroy($bankId);

        return response()->json([
            'message' => 'Banco deletado com sucesso',
        ], 204);
    }
}