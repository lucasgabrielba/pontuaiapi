<?php

namespace App\Http\Controllers\Cards;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cards\StoreCardRequest;
use App\Http\Requests\Cards\UpdateCardRequest;
use Domains\Cards\Services\CardsService;
use Illuminate\Http\Request;

class CardsController extends Controller
{
    protected CardsService $cardsService;

    public function __construct(CardsService $cardsService)
    {
        $this->cardsService = $cardsService;
    }

    public function index(Request $request)
    {
        $filters = $request->all();
        $cards = $this->cardsService->list($filters);

        return response()->json($cards);
    }

    public function hasCards()
    {
        $hasCards = $this->cardsService->hasCards();

        return response()->json($hasCards);
    }

    public function store(StoreCardRequest $request)
    {
        $data = $request->validated();
        $card = $this->cardsService->create($data);

        return response()->json($card, 201);
    }

    public function show(string $cardId)
    {
        $card = $this->cardsService->get($cardId);

        return response()->json($card);
    }

    public function update(UpdateCardRequest $request, string $cardId)
    {
        $data = $request->validated();
        $this->cardsService->update($cardId, $data);

        return response()->json([
            'message' => 'Cartão atualizado com sucesso',
        ]);
    }

    public function destroy(string $cardId)
    {
        $this->cardsService->destroy($cardId);

        return response()->json([
            'message' => 'Cartão deletado com sucesso',
        ], 204);
    }

    public function invoices(string $cardId)
    {
        $invoices = $this->cardsService->getInvoices($cardId);

        return response()->json($invoices);
    }
}