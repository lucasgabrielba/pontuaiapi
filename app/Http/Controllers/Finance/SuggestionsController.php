<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CreateSuggestionRequest;
use Domains\Finance\Services\SuggestionsService;
use Illuminate\Http\Request;

class SuggestionsController extends Controller
{
    protected SuggestionsService $suggestionsService;

    public function __construct(SuggestionsService $suggestionsService)
    {
        $this->suggestionsService = $suggestionsService;
    }

    /**
     * Lista todas as sugestões com filtros
     */
    public function index(Request $request)
    {
        $filters = $request->all();
        $suggestions = $this->suggestionsService->list($filters);

        return response()->json($suggestions);
    }

    /**
     * Cria uma nova sugestão para uma fatura
     */
    public function store(CreateSuggestionRequest $request, string $invoiceId)
    {
        $data = $request->validated();
        $data['invoice_id'] = $invoiceId; // Add invoice_id to the data array
        $suggestion = $this->suggestionsService->create($data);

        return response()->json([
            'message' => 'Sugestão criada com sucesso',
            'data' => $suggestion
        ], 201);
    }

    /**
     * Exibe uma sugestão específica
     */
    public function show(string $suggestionId)
    {
        $suggestion = $this->suggestionsService->get($suggestionId);

        return response()->json($suggestion);
    }

    /**
     * Atualiza uma sugestão
     */
    public function update(CreateSuggestionRequest $request, string $suggestionId)
    {
        $data = $request->validated();
        $this->suggestionsService->update($suggestionId, $data);

        return response()->json([
            'message' => 'Sugestão atualizada com sucesso'
        ]);
    }

    /**
     * Remove uma sugestão
     */
    public function destroy(string $suggestionId)
    {
        $this->suggestionsService->destroy($suggestionId);

        return response()->json([
            'message' => 'Sugestão deletada com sucesso'
        ], 204);
    }

    /**
     * Lista sugestões de uma fatura específica
     */
    public function getByInvoice(string $invoiceId)
    {
        $suggestions = $this->suggestionsService->getByInvoice($invoiceId);

        return response()->json($suggestions);
    }

    /**
     * Estatísticas das sugestões por fatura
     */
    public function getStatsByInvoice(string $invoiceId)
    {
        $stats = $this->suggestionsService->getStatsByInvoice($invoiceId);

        return response()->json($stats);
    }
}