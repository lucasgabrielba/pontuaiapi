<?php

namespace Domains\Finance\Services;

use Domains\Cards\Models\Card;
use Domains\Finance\Models\Transaction;
use Domains\Rewards\Models\Point;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalysisService
{
    protected AIRecommendationService $aiRecommendationService;
    
    public function __construct(?AIRecommendationService $aiRecommendationService = null)
    {
        $this->aiRecommendationService = $aiRecommendationService ?? new AIRecommendationService();
    }
    
    /**
     * Gera recomendações de cartões com base nos padrões de gasto.
     */
    public function getCardsRecommendation(string $userId): array
    {
        try {
            Log::info('Iniciando análise para recomendação de cartões', [
                'user_id' => $userId
            ]);
            
            // Verificar se o usuário tem dados suficientes para análise
            $hasEnoughData = $this->hasEnoughDataForAnalysis($userId);
            
            if (!$hasEnoughData) {
                Log::info('Usuário não tem dados suficientes para análise completa', [
                    'user_id' => $userId
                ]);
                
                return [
                    'message' => 'Adicione mais faturas e transações para receber recomendações personalizadas.',
                    'recommendations' => $this->getBasicRecommendations($userId)
                ];
            }
            
            // Usar o serviço de IA para gerar recomendações personalizadas
            $aiRecommendations = $this->aiRecommendationService->getCardRecommendations($userId);
            
            // Adicionar informações adicionais ao resultado
            $result = $aiRecommendations;
            
            // Adicionar categorias e estabelecimentos principais para contextualização na UI
            $result['top_categories'] = $this->getTopCategories($userId);
            $result['top_merchants'] = $this->getTopMerchants($userId);
            
            Log::info('Recomendações de cartões geradas com sucesso', [
                'user_id' => $userId,
                'recommendation_count' => count($result['recommendations'] ?? [])
            ]);
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Erro ao gerar recomendações de cartões', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Em caso de erro, retornar recomendações básicas
            return [
                'message' => 'Não foi possível gerar recomendações personalizadas. Usando recomendações genéricas.',
                'error' => $e->getMessage(),
                'recommendations' => $this->getBasicRecommendations($userId)
            ];
        }
    }
    
    /**
     * Gerar recomendações para otimização de transações específicas
     */
    public function getTransactionOptimizations(string $userId): array
    {
        try {
            // Verificar se o usuário tem dados suficientes
            $hasRecentTransactions = Transaction::join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
                ->where('invoices.user_id', $userId)
                ->where('transaction_date', '>=', now()->subDays(30))
                ->exists();
                
            if (!$hasRecentTransactions) {
                return [
                    'message' => 'Adicione faturas recentes para receber análises de otimização.',
                    'optimizations' => []
                ];
            }
            
            // Usar o serviço de IA para analisar transações
            return $this->aiRecommendationService->getTransactionOptimizationRecommendations($userId);
            
        } catch (\Exception $e) {
            Log::error('Erro ao gerar otimizações de transações', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'message' => 'Não foi possível analisar suas transações. Tente novamente mais tarde.',
                'error' => $e->getMessage(),
                'optimizations' => []
            ];
        }
    }
    
    /**
     * Analisa padrões de gasto por categoria e período.
     */
    public function getSpendingPatterns(string $userId, string $startDate, string $endDate): array
    {
        // Gastos por categoria
        $categorySpending = Transaction::select(
                'categories.name as category', 
                'categories.color',
                'categories.icon',
                DB::raw('SUM(transactions.amount) as total'),
                DB::raw('COUNT(transactions.id) as count')
            )
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('invoices.user_id', $userId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->groupBy('categories.id', 'categories.name', 'categories.color', 'categories.icon')
            ->orderBy('total', 'desc')
            ->get()
            ->toArray();
        
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
            ->get()
            ->toArray();
        
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
            ->get()
            ->toArray();
        
        // Obter análise de otimização para o período especificado
        $optimizationOpportunities = [];
        
        try {
            // Análise simplificada baseada em regras predefinidas
            $optimizationOpportunities = $this->analyzeOptimizationOpportunities($userId, $startDate, $endDate);
        } catch (\Exception $e) {
            Log::error('Erro ao analisar oportunidades de otimização', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
        
        return [
            'category_spending' => $categorySpending,
            'monthly_spending' => $monthlySpending,
            'merchant_average' => $merchantAverage,
            'optimization_opportunities' => $optimizationOpportunities
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
                'reward_programs.id as program_id',
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
        
        // Tentar gerar recomendações personalizadas de uso de pontos
        $recommendations = [];
        
        try {
            // Aqui você poderia implementar uma chamada à IA para analisar o uso de pontos
            // Por ora, vamos usar recomendações estáticas baseadas nos dados
            $recommendations = $this->generatePointsRecommendations($pointsByProgram, $expiringPoints);
        } catch (\Exception $e) {
            Log::error('Erro ao gerar recomendações de uso de pontos', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
        
        return [
            'points_by_program' => $pointsByProgram,
            'expiring_points' => $expiringPoints,
            'monthly_accumulation' => $monthlyAccumulation,
            'recommendations' => $recommendations
        ];
    }
    
    /**
     * Verifica se o usuário tem dados suficientes para análise avançada
     */
    private function hasEnoughDataForAnalysis(string $userId): bool
    {
        // Verificar se o usuário tem pelo menos 2 cartões cadastrados
        $cardCount = Card::where('user_id', $userId)->count();
        
        // Verificar se o usuário tem pelo menos 10 transações nos últimos 3 meses
        $transactionCount = Transaction::join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->where('invoices.user_id', $userId)
            ->where('transaction_date', '>=', now()->subMonths(3))
            ->count();
            
        // Verificar se o usuário tem pelo menos 3 categorias diferentes de transações
        $categoryCount = Transaction::join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->where('invoices.user_id', $userId)
            ->where('transaction_date', '>=', now()->subMonths(3))
            ->whereNotNull('category_id')
            ->distinct('category_id')
            ->count('category_id');
            
        // Considerar que tem dados suficientes se atender a pelo menos 2 dos 3 critérios
        $criteriaCount = 0;
        if ($cardCount >= 1) $criteriaCount++;
        if ($transactionCount >= 10) $criteriaCount++;
        if ($categoryCount >= 3) $criteriaCount++;
        
        return $criteriaCount >= 2;
    }
    
    /**
     * Obtém as principais categorias de gastos do usuário
     */
    private function getTopCategories(string $userId, int $limit = 5): array
    {
        return Transaction::select('categories.id', 'categories.name', 'categories.icon', 'categories.color')
            ->selectRaw('SUM(transactions.amount) as total')
            ->selectRaw('COUNT(transactions.id) as count')
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('invoices.user_id', $userId)
            ->where('transaction_date', '>=', now()->subMonths(3))
            ->groupBy('categories.id', 'categories.name', 'categories.icon', 'categories.color')
            ->orderBy('total', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
    
    /**
     * Obtém os principais estabelecimentos frequentados pelo usuário
     */
    private function getTopMerchants(string $userId, int $limit = 10): array
    {
        return Transaction::select('merchant_name')
            ->selectRaw('SUM(amount) as total')
            ->selectRaw('COUNT(*) as frequency')
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->where('invoices.user_id', $userId)
            ->where('transaction_date', '>=', now()->subMonths(3))
            ->groupBy('merchant_name')
            ->orderBy('total', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
    
    /**
     * Gera recomendações básicas quando não há dados suficientes
     */
    private function getBasicRecommendations(string $userId): array
    {
        // Obtém os cartões do usuário
        $userCards = Card::where('user_id', $userId)->get();
        
        if ($userCards->isEmpty()) {
            // Recomendações genéricas para quem não tem cartões
            return [
                [
                    'card_name' => 'Cartão Platinum',
                    'description' => 'Oferece 2.5x pontos em restaurantes e supermercados',
                    'annual_fee' => 49900, // 499.00
                    'potential_points_increase' => '100%',
                    'analysis' => 'Ideal para começar a acumular pontos com gastos em alimentação'
                ],
                [
                    'card_name' => 'Cartão Black',
                    'description' => 'Oferece 3x pontos em combustível e farmácias',
                    'annual_fee' => 89900, // 899.00
                    'potential_points_increase' => '150%',
                    'analysis' => 'Excelente para quem faz gastos frequentes com combustível e saúde'
                ]
            ];
        }
        
        // Caso o usuário já tenha cartões, tentar fazer recomendações básicas
        // baseadas nos cartões que ele já possui
        $recommendations = [];
        
        foreach ($userCards as $card) {
            // Verificar se o cartão tem taxa de conversão baixa
            if ($card->conversion_rate < 1.5) {
                $recommendations[] = [
                    'card_name' => 'Cartão Premium',
                    'description' => 'Oferece taxa de conversão superior ao seu cartão atual',
                    'annual_fee' => 59900, // 599.00
                    'potential_points_increase' => '50%',
                    'analysis' => 'Substituto para seu cartão ' . $card->name . ' com maior conversão de pontos'
                ];
            }
            
            // Adicionar mais regras específicas conforme necessário
        }
        
        // Se ainda não tiver pelo menos 2 recomendações, adicionar genéricas
        if (count($recommendations) < 2) {
            $recommendations[] = [
                'card_name' => 'Cartão Infinite',
                'description' => 'Oferece 4x pontos em todas as categorias',
                'annual_fee' => 129900, // 1299.00
                'potential_points_increase' => '200%',
                'analysis' => 'Para quem busca o máximo de benefícios e acúmulo de pontos'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Analisa oportunidades de otimização de gastos
     */
    private function analyzeOptimizationOpportunities(string $userId, string $startDate, string $endDate): array
    {
        // Buscar categorias com maior volume de gastos
        $topCategories = Transaction::select('categories.id', 'categories.name')
            ->selectRaw('SUM(transactions.amount) as total')
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('invoices.user_id', $userId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total', 'desc')
            ->limit(3)
            ->get();
            
        // Buscar os cartões do usuário
        $userCards = Card::where('user_id', $userId)->get();
        
        $opportunities = [];
        
        // Para cada categoria principal, identificar se existe um cartão melhor
        foreach ($topCategories as $category) {
            // Identificar o cartão com melhor taxa para esta categoria (simulação)
            $bestCard = null;
            $bestRate = 0;
            
            foreach ($userCards as $card) {
                // Simular taxas especiais para diferentes categorias
                $categoryBonus = $this->getCategoryBonus($card, $category->name);
                $effectiveRate = $card->conversion_rate * $categoryBonus;
                
                if ($effectiveRate > $bestRate) {
                    $bestRate = $effectiveRate;
                    $bestCard = $card;
                }
            }
            
            // Se encontrou um cartão com bônus para esta categoria
            if ($bestCard && $bestRate > $bestCard->conversion_rate) {
                $opportunities[] = [
                    'category' => $category->name,
                    'spending' => $category->total / 100, // Converter de centavos para reais
                    'card_name' => $bestCard->name,
                    'potential_increase' => round(($bestRate / $bestCard->conversion_rate - 1) * 100) . '%',
                    'recommendation' => "Use o cartão {$bestCard->name} para gastos em {$category->name} para ganhar {$bestRate}x pontos."
                ];
            }
        }
        
        return $opportunities;
    }
    
    /**
     * Simula bônus de pontos para categorias específicas
     */
    private function getCategoryBonus($card, $categoryName): float
    {
        // Simulação de bônus para diferentes tipos de cartões e categorias
        $bonusMap = [
            'Platinum' => [
                'Restaurante' => 2.5,
                'Supermercado' => 2.0,
                'Viagem' => 1.5,
                'default' => 1.0
            ],
            'Black' => [
                'Combustível' => 3.0,
                'Farmácia' => 2.5,
                'Viagem' => 2.0,
                'default' => 1.0
            ],
            'Infinite' => [
                'Restaurante' => 2.0,
                'Supermercado' => 2.0,
                'Combustível' => 2.0,
                'Viagem' => 3.0,
                'default' => 1.5
            ],
            'default' => [
                'default' => 1.0
            ]
        ];
        
        // Determinar o tipo de cartão
        $cardType = 'default';
        foreach (['Platinum', 'Black', 'Infinite'] as $type) {
            if (stripos($card->name, $type) !== false) {
                $cardType = $type;
                break;
            }
        }
        
        // Obter o mapa de bônus para este tipo de cartão
        $bonuses = $bonusMap[$cardType] ?? $bonusMap['default'];
        
        // Retornar o bônus específico para a categoria ou o padrão
        return $bonuses[$categoryName] ?? $bonuses['default'] ?? 1.0;
    }
    
    /**
     * Gera recomendações para uso de pontos
     */
    private function generatePointsRecommendations($pointsByProgram, $expiringPoints): array
    {
        $recommendations = [
            'message' => 'Veja como otimizar o uso dos seus pontos:',
            'suggested_actions' => []
        ];
        
        // Verificar se há pontos prestes a expirar
        if ($expiringPoints->isNotEmpty()) {
            $totalExpiring = $expiringPoints->sum('amount');
            $nearestExpirationDate = $expiringPoints->min('expiration_date');
            
            $recommendations['suggested_actions'][] = "Você tem {$totalExpiring} pontos que expiram em breve (próxima expiração: {$nearestExpirationDate->format('d/m/Y')}). Considere utilizar estes pontos primeiro.";
        }
        
        // Verificar se o usuário tem pontos suficientes para uma passagem aérea
        $hasEnoughForFlight = false;
        foreach ($pointsByProgram as $program) {
            if ($program->total >= 20000) {
                $hasEnoughForFlight = true;
                $recommendations['suggested_actions'][] = "Você tem {$program->total} pontos no programa {$program->program}, o suficiente para uma passagem aérea nacional. Verifique promoções atuais.";
            }
        }
        
        // Verificar se vale a pena transferir pontos entre programas
        if ($pointsByProgram->count() > 1) {
            $recommendations['suggested_actions'][] = "Você tem pontos em múltiplos programas. Considere consolidar seus pontos em um único programa para alcançar prêmios maiores.";
        }
        
        // Sugestões genéricas adicionais
        $recommendations['suggested_actions'][] = "Verifique promoções de transferência com bônus para aumentar o valor dos seus pontos.";
        
        if (!$hasEnoughForFlight) {
            $recommendations['suggested_actions'][] = "Continue acumulando pontos para atingir prêmios mais valiosos como passagens aéreas (geralmente a partir de 20.000 pontos).";
        }
        
        return $recommendations;
    }
}