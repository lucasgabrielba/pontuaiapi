<?php

namespace Domains\Finance\Services;

use Domains\Cards\Models\Card;
use Domains\Finance\Models\Transaction;
use Domains\Rewards\Models\Point;
use Illuminate\Support\Facades\DB;

class AnalysisService
{
    /**
     * Gera recomendações de cartões com base nos padrões de gasto.
     */
    public function getCardsRecommendation(string $userId): array
    {
        // Obtém os cartões do usuário
        $userCards = Card::where('user_id', $userId)->get();
        
        // Obtém as categorias com mais gastos nos últimos 3 meses
        $topCategories = Transaction::select('category_id', DB::raw('SUM(amount) as total'))
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->where('invoices.user_id', $userId)
            ->where('transaction_date', '>=', now()->subMonths(3))
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->orderBy('total', 'desc')
            ->limit(3)
            ->get()
            ->pluck('category_id')
            ->toArray();
        
        // Obtém estabelecimentos mais frequentes
        $topMerchants = Transaction::select('merchant_name', DB::raw('COUNT(*) as count'))
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->where('invoices.user_id', $userId)
            ->where('transaction_date', '>=', now()->subMonths(3))
            ->groupBy('merchant_name')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->pluck('merchant_name')
            ->toArray();
        
        // Aqui, em produção, usaríamos o serviço de IA para analisar os dados
        // e gerar recomendações personalizadas. Para este exemplo, usamos uma simulação.
        
        return [
            'recommendations' => [
                [
                    'card_name' => 'Cartão Platinum',
                    'description' => 'Oferece 2.5x pontos em restaurantes e supermercados',
                    'annual_fee' => 49900, // 499.00
                    'estimated_points' => 15000,
                    'roi' => 'Alto',
                ],
                [
                    'card_name' => 'Cartão Infinite',
                    'description' => 'Oferece 3x pontos em combustível e farmácias',
                    'annual_fee' => 89900, // 899.00
                    'estimated_points' => 25000,
                    'roi' => 'Médio',
                ],
            ],
            'top_categories' => $topCategories,
            'top_merchants' => $topMerchants,
        ];
    }
    
    /**
     * Analisa padrões de gasto por categoria e período.
     */
    public function getSpendingPatterns(string $userId, string $startDate, string $endDate): array
    {
        // Gastos por categoria
        $categorySpending = Transaction::select(
                'categories.name as category', 
                DB::raw('SUM(transactions.amount) as total')
            )
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('invoices.user_id', $userId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total', 'desc')
            ->get();
        
        // Gastos mensais
        $monthlySpending = Transaction::select(
                DB::raw('DATE_FORMAT(transaction_date, "%Y-%m") as month'),
                DB::raw('SUM(amount) as total')
            )
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->where('invoices.user_id', $userId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month')
            ->get();
        
        // Média de gastos por estabelecimento
        $merchantAverage = Transaction::select(
                'merchant_name',
                DB::raw('AVG(amount) as average'),
                DB::raw('COUNT(*) as frequency')
            )
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->where('invoices.user_id', $userId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->groupBy('merchant_name')
            ->having('frequency', '>', 1)
            ->orderBy('average', 'desc')
            ->limit(10)
            ->get();
        
        return [
            'category_spending' => $categorySpending,
            'monthly_spending' => $monthlySpending,
            'merchant_average' => $merchantAverage
        ];
    }
    
    /**
     * Resumo de pontos e recomendações de uso.
     */
    public function getPointsSummary(string $userId): array
    {
        // Pontos por programa de recompensas
        $pointsByProgram = Point::select(
                'reward_programs.name as program',
                DB::raw('SUM(points.amount) as total')
            )
            ->join('reward_programs', 'points.reward_program_id', '=', 'reward_programs.id')
            ->where('points.user_id', $userId)
            ->where('points.status', 'Ativo')
            ->groupBy('reward_programs.id', 'reward_programs.name')
            ->get();
        
        // Pontos a expirar nos próximos 90 dias
        $expiringPoints = Point::select(
                'reward_programs.name as program',
                'points.amount',
                'points.expiration_date'
            )
            ->join('reward_programs', 'points.reward_program_id', '=', 'reward_programs.id')
            ->where('points.user_id', $userId)
            ->where('points.status', 'Ativo')
            ->whereNotNull('points.expiration_date')
            ->where('points.expiration_date', '<=', now()->addDays(90))
            ->orderBy('points.expiration_date')
            ->get();
        
        // Histórico de acúmulo mensal
        $monthlyAccumulation = Point::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(amount) as total')
            )
            ->where('user_id', $userId)
            ->where('status', 'Ativo')
            ->groupBy('month')
            ->orderBy('month')
            ->limit(12)
            ->get();
        
        return [
            'points_by_program' => $pointsByProgram,
            'expiring_points' => $expiringPoints,
            'monthly_accumulation' => $monthlyAccumulation,
            'recommendations' => [
                'message' => 'Você tem pontos a expirar em breve. Considere utilizar para uma passagem aérea.',
                'suggested_actions' => [
                    'Transfira pontos entre programas para unificar',
                    'Utilize pontos que expiram em breve para produtos',
                    'Verifique promoções de transferência com bônus'
                ]
            ]
        ];
    }
}