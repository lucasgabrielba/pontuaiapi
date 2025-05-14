<?php

namespace App\Http\Controllers\Cards;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cards\StoreRewardProgramRequest;
use App\Http\Requests\Cards\UpdateRewardProgramRequest;
use Domains\Cards\Services\RewardProgramsService;
use Illuminate\Http\Request;

class RewardProgramsController extends Controller
{
    protected RewardProgramsService $rewardProgramsService;

    public function __construct(RewardProgramsService $rewardProgramsService)
    {
        $this->rewardProgramsService = $rewardProgramsService;
    }

    /**
     * Display a listing of the reward programs.
     */
    public function index(Request $request)
    {
        $filters = $request->all();
        $programs = $this->rewardProgramsService->list($filters);

        return response()->json($programs);
    }

    /**
     * Store a newly created reward program in storage.
     */
    public function store(StoreRewardProgramRequest $request)
    {
        $data = $request->validated();
        $program = $this->rewardProgramsService->create($data);

        return response()->json($program, 201);
    }

    /**
     * Display the specified reward program.
     */
    public function show(string $programId)
    {
        $program = $this->rewardProgramsService->get($programId);

        return response()->json($program);
    }

    /**
     * Update the specified reward program in storage.
     */
    public function update(UpdateRewardProgramRequest $request, string $programId)
    {
        $data = $request->validated();
        $this->rewardProgramsService->update($programId, $data);

        return response()->json([
            'message' => 'Programa de recompensas atualizado com sucesso',
        ]);
    }

    /**
     * Remove the specified reward program from storage.
     */
    public function destroy(string $programId)
    {
        $this->rewardProgramsService->destroy($programId);

        return response()->json([
            'message' => 'Programa de recompensas deletado com sucesso',
        ], 204);
    }

    /**
     * Get all cards associated with a reward program.
     */
    public function cards(string $programId)
    {
        $cards = $this->rewardProgramsService->getLinkedCards($programId);

        return response()->json($cards);
    }
}