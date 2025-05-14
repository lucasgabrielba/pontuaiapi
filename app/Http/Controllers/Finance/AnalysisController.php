<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Domains\Finance\Services\AnalysisService;
use Illuminate\Http\Request;

class AnalysisController extends Controller
{
    protected AnalysisService $analysisService;

    public function __construct(AnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    /**
     * Gera recomendações de cartões com base nos padrões de gasto.
     */
    public function cardsRecommendation(Request $request)
    {
        $results = $this->analysisService->getCardsRecommendation(auth()->id());
        return response()->json($results);
    }
    
    /**
     * Analisa padrões de gasto por categoria e período.
     */
    public function spendingPatterns(Request $request)
    {
        // Filtros
        $startDate = $request->input('start_date', now()->subMonths(6)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        
        $results = $this->analysisService->getSpendingPatterns(
            auth()->id(),
            $startDate,
            $endDate
        );
        
        return response()->json($results);
    }
    
    /**
     * Resumo de pontos e recomendações de uso.
     */
    public function pointsSummary(Request $request)
    {
        $results = $this->analysisService->getPointsSummary(auth()->id());
        return response()->json($results);
    }
}