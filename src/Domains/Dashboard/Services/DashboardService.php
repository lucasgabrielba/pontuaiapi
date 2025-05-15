<?php

namespace Domains\Dashboard\Services;

use Domains\Cards\Models\Card;
use Domains\Finance\Models\Transaction;
use Domains\Finance\Models\Invoice;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Obtém todos os dados do dashboard
     */
    public function getDashboardData(): array
    {
        return [
            'stats' => $this->getStats(),
            'transactions' => $this->getTransactions(),
            'pointsPrograms' => $this->getPointsPrograms(),
            'pointsByCategory' => $this->getPointsByCategory(),
            'monthlySpent' => $this->getMonthlySpent(),
            'recommendations' => $this->getRecommendations(),
        ];
    }
    
    /**
     * Obtém estatísticas gerais do dashboard
     */
    public function getStats(): array
    {
        $userId = auth()->id();
        
        // Gastos totais (últimos 30 dias)
        $totalSpent = Invoice::where('user_id', $userId)
            ->where('reference_date', '>=', now()->subDays(30))
            ->sum('total_amount') / 100; // Convertendo de centavos para reais
        
        // Pontos ganhos (últimos 30 dias)
        $pointsEarned = Transaction::join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->where('invoices.user_id', $userId)
            ->where('transactions.transaction_date', '>=', now()->subDays(30))
            ->sum('points_earned');
        
        // Pontos potenciais (que poderiam ter sido ganhos com otimização)
        $potentialPoints = (int)($pointsEarned * 1.8); // Simulando aumento de aprox. 80% com otimização
        
        // Cartões ativos
        $activeCards = Card::where('user_id', $userId)
            ->where('active', true)
            ->count();
        
        // Crescimento de gastos (comparado com mês anterior)
        $currentMonthSpent = Invoice::where('user_id', $userId)
            ->where('reference_date', '>=', now()->startOfMonth())
            ->sum('total_amount');
            
        $previousMonthSpent = Invoice::where('user_id', $userId)
            ->where('reference_date', '>=', now()->subMonth()->startOfMonth())
            ->where('reference_date', '<', now()->startOfMonth())
            ->sum('total_amount');
        
        $spentGrowth = 0;
        if ($previousMonthSpent > 0) {
            $spentGrowth = (($currentMonthSpent - $previousMonthSpent) / $previousMonthSpent) * 100;
        }
        
        return [
            'totalSpent' => $totalSpent,
            'pointsEarned' => $pointsEarned,
            'potentialPoints' => $potentialPoints,
            'activeCards' => $activeCards,
            'spentGrowth' => $spentGrowth,
        ];
    }
    
    /**
     * Obtém transações recentes para o dashboard
     */
    public function getTransactions(): array
    {
        $userId = auth()->id();
        
        // Busca as 5 transações mais recentes
        $transactions = Transaction::join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('invoices.user_id', $userId)
            ->select([
                'transactions.id',
                'transactions.merchant_name as merchant',
                'categories.name as category',
                'transactions.amount',
                'transactions.points_earned as points',
                'transactions.is_recommended'
            ])
            ->orderBy('transactions.transaction_date', 'desc')
            ->limit(5)
            ->get();
        
        // Formata os valores monetários
        return $transactions->map(function ($transaction) {
            $transaction->amount = 'R$ ' . number_format($transaction->amount / 100, 2, ',', '.');
            $transaction->merchantLogo = '/placeholder.svg'; // Logo placeholder por enquanto
            return $transaction;
        })->toArray();
    }
    
    /**
     * Obtém programas de pontos para o dashboard
     */
    public function getPointsPrograms(): array
    {
        $userId = auth()->id();
        
        // Busca programas de recompensas e pontos
        $programs = DB::table('points')
            ->join('reward_programs', 'points.reward_program_id', '=', 'reward_programs.id')
            ->where('points.user_id', $userId)
            ->select(
                'reward_programs.name',
                DB::raw('SUM(points.amount) as value')
            )
            ->groupBy('reward_programs.name')
            ->get();
        
        // Define cores para cada programa (poderia ser dinâmico ou vir do banco)
        $colors = [
            'Livelo' => '#ff6b6b',
            'Smiles' => '#feca57',
            'Esfera' => '#48dbfb',
            'TudoAzul' => '#1dd1a1',
            'Dotz' => '#5f27cd',
        ];
        
        $otherTotal = 0;
        $result = [];
        
        // Formato e filtra os 3 maiores programas, agrupando o resto em "Outros"
        foreach ($programs as $index => $program) {
            if ($index < 3) {
                $result[] = [
                    'name' => $program->name,
                    'value' => $program->value,
                    'color' => $colors[$program->name] ?? '#999999',
                ];
            } else {
                $otherTotal += $program->value;
            }
        }
        
        // Adiciona a categoria "Outros" se houver mais de 3 programas
        if ($otherTotal > 0) {
            $result[] = [
                'name' => 'Outros',
                'value' => $otherTotal,
                'color' => '#1dd1a1',
            ];
        }
        
        return $result;
    }
    
    /**
     * Obtém pontos por categoria para o dashboard
     */
    public function getPointsByCategory(): array
    {
        $userId = auth()->id();
        
        // Busca categorias e pontos ganhos
        $categoriesPoints = DB::table('transactions')
            ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('invoices.user_id', $userId)
            ->select(
                'categories.name as nome',
                DB::raw('SUM(transactions.points_earned) as pontos_ganhos')
            )
            ->groupBy('categories.name')
            ->orderBy('pontos_ganhos', 'desc')
            ->limit(4)
            ->get();
        
        // Calcular pontos potenciais (simulação)
        $result = [];
        foreach ($categoriesPoints as $category) {
            // Simula pontos potenciais (aproximadamente 2-3x mais do que os ganhos)
            $pontosPotenciais = round($category->pontos_ganhos * (rand(20, 30) / 10));
            
            $result[] = [
                'nome' => $category->nome,
                'pontosGanhos' => $category->pontos_ganhos,
                'pontosPotenciais' => $pontosPotenciais
            ];
        }
        
        return $result;
    }
    
    /**
     * Obtém dados de gastos mensais para o dashboard
     */
    public function getMonthlySpent(): array
    {
        $userId = auth()->id();
        
        // Busca gastos mensais dos últimos 6 meses - usando formato PostgreSQL para as datas
        $monthlyData = Invoice::where('user_id', $userId)
            ->where('reference_date', '>=', now()->subMonths(6))
            ->select(
                DB::raw("to_char(reference_date, 'Mon') as name"),
                DB::raw('SUM(total_amount)/100 as total')
            )
            ->groupBy('name')
            ->orderBy('name')
            ->get();
            
        // Se não houver dados suficientes, gera dados fictícios para exemplo
        if ($monthlyData->count() < 6) {
            $existingMonths = $monthlyData->pluck('name')->toArray();
            $months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'];
            
            foreach ($months as $month) {
                if (!in_array($month, $existingMonths)) {
                    // Gera valor aleatório entre 1500 e 3500
                    $monthlyData->push((object)[
                        'name' => $month,
                        'total' => rand(1500, 3500)
                    ]);
                }
            }
        }
        
        return $monthlyData->toArray();
    }
    
    /**
     * Obtém recomendações para o dashboard
     */
    public function getRecommendations(): array
    {
        $userId = auth()->id();
        
        // Em um ambiente real, essas recomendações seriam geradas com base em análises
        // dos padrões de gasto do usuário, comparando com opções disponíveis no mercado
        
        // Para demonstração, vamos usar recomendações pré-definidas
        $recommendations = [
            [
                'id' => 1,
                'title' => "Supermercado - Mude de estabelecimento",
                'description' => "Você gastou R$ 1.235,00 em supermercados este mês. Recomendamos trocar de estabelecimento para maximizar pontos.",
                'type' => "merchant",
                'recommendation' => "Carrefour oferece 4x mais pontos com seu cartão atual",
                'potentialGain' => 200,
            ],
            [
                'id' => 2,
                'title' => "Postos de combustível - Use outro cartão",
                'description' => "Seus gastos em postos são significativos (R$ 420,00/mês). Um cartão específico seria melhor.",
                'type' => "card",
                'recommendation' => "Cartão Shell Box Itaucard Platinum",
                'potentialGain' => 120,
            ],
            [
                'id' => 3,
                'title' => "Restaurantes - Concentre seus gastos",
                'description' => "Você frequenta restaurantes diversificados. Concentre gastos em estabelecimentos parceiros.",
                'type' => "merchant",
                'recommendation' => "Rede Outback (5x mais pontos às terças-feiras)",
                'potentialGain' => 85,
            ],
            [
                'id' => 4,
                'title' => "Streaming - Agrupe serviços",
                'description' => "Você paga por vários serviços de streaming separadamente. Agrupe para maximizar pontos.",
                'type' => "merchant",
                'recommendation' => "Plano combo na Amazon Prime",
                'potentialGain' => 45,
            ],
        ];
        
        return $recommendations;
    }
}