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

    /**
     * Display a listing of the cards.
     */
    public function index(Request $request)
    {
        $filters = $request->all();
        $cards = $this->cardsService->list($filters);

        return response()->json($cards);
    }

    /**
     * Store a newly created card in storage.
     */
    public function store(StoreCardRequest $request)
    {
        $data = $request->validated();
        $card = $this->cardsService->create($data);

        return response()->json($card, 201);
    }

    /**
     * Display the specified card.
     */
    public function show(string $cardId)
    {
        $card = $this->cardsService->get($cardId);

        return response()->json($card);
    }

    /**
     * Update the specified card in storage.
     */
    public function update(UpdateCardRequest $request, string $cardId)
    {
        $data = $request->validated();
        $this->cardsService->update($cardId, $data);

        return response()->json([
            'message' => 'Cartão atualizado com sucesso',
        ]);
    }

    /**
     * Remove the specified card from storage.
     */
    public function destroy(string $cardId)
    {
        $this->cardsService->destroy($cardId);

        return response()->json([
            'message' => 'Cartão deletado com sucesso',
        ], 204);
    }

    /**
     * Get all invoices for a specific card.
     */
    public function invoices(string $cardId)
    {
        $invoices = $this->cardsService->getInvoices($cardId);

        return response()->json($invoices);
    }
}