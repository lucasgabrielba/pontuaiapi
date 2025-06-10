<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Domains\Finance\Services\AnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AnalysisController extends Controller
{
    protected AnalysisService $analysisService;

    public function __construct(AnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    /**
     * Gera recomendações de cartões com base nos padrões de gasto.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cardsRecommendation(Request $request)
    {
        try {
            $userId = auth()->id();
            $results = $this->analysisService->getCardsRecommendation($userId);
            
            return response()->json($results);
        } catch (\Exception $e) {
            Log::error('Erro ao processar recomendações de cartões', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Não foi possível gerar recomendações. Tente novamente mais tarde.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Retorna sugestões de otimização para transações específicas.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transactionOptimizations(Request $request)
    {
        try {
            $userId = auth()->id();
            $results = $this->analysisService->getTransactionOptimizations($userId);
            
            return response()->json($results);
        } catch (\Exception $e) {
            Log::error('Erro ao processar otimizações de transações', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Não foi possível analisar transações. Tente novamente mais tarde.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Analisa padrões de gasto por categoria e período.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function spendingPatterns(Request $request)
    {
        try {
            // Filtros
            $startDate = $request->input('start_date', now()->subMonths(6)->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));
            
            $results = $this->analysisService->getSpendingPatterns(
                auth()->id(),
                $startDate,
                $endDate
            );
            
            return response()->json($results);
        } catch (\Exception $e) {
            Log::error('Erro ao analisar padrões de gasto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Não foi possível analisar padrões de gasto. Tente novamente mais tarde.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Resumo de pontos e recomendações de uso.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pointsSummary(Request $request)
    {
        try {
            $results = $this->analysisService->getPointsSummary(auth()->id());
            return response()->json($results);
        } catch (\Exception $e) {
            Log::error('Erro ao gerar resumo de pontos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Não foi possível gerar resumo de pontos. Tente novamente mais tarde.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}