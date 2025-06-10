<?php

namespace Domains\Finance\Services;

use Domains\Cards\Models\Card;
use Domains\Cards\Models\RewardProgram;
use Domains\Finance\Models\Category;
use Domains\Finance\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIRecommendationService
{
  protected string $apiKey;
  protected string $model;

  public function __construct()
  {
    $this->apiKey = config('services.openai.key');
    $this->model = config('services.openai.recommendation_model', 'gpt-3.5-turbo');

    if (!$this->apiKey) {
      Log::error('API Key da OpenAI não configurada para recomendações');
      throw new \Exception('API Key da OpenAI não configurada');
    }
  }

  /**
   * Gera recomendações de cartões baseadas no histórico de gastos
   * @param string $userId ID do usuário
   * @return array Recomendações de cartões
   */
  public function getCardRecommendations(string $userId): array
  {
    try {
      Log::info('Iniciando recomendações de cartões', ['user_id' => $userId]);

      // 1. Obter cartões atuais do usuário
      $userCards = Card::where('user_id', $userId)
        ->where('active', true)
        ->get()
        ->toArray();

      if (empty($userCards)) {
        return [
          'recommendations' => [],
          'message' => 'Você precisa adicionar cartões para receber recomendações personalizadas.'
        ];
      }

      // 2. Obter principais categorias de gastos (últimos 3 meses)
      $topCategories = $this->getTopSpendingCategories($userId);

      // 3. Obter principais estabelecimentos
      $topMerchants = $this->getTopMerchants($userId);

      // 4. Obter estatísticas de gastos mensais
      $spendingStats = $this->getSpendingStatistics($userId);

      // 5. Obter cartões disponíveis no sistema (que o usuário não possui)
      $availableCards = $this->getAvailableCards($userId);

      // 6. Obter programas de recompensas disponíveis
      $rewardPrograms = RewardProgram::all()->toArray();

      // Preparar os dados para enviar à API
      $analysisData = [
        'user_cards' => $userCards,
        'top_categories' => $topCategories,
        'top_merchants' => $topMerchants,
        'spending_stats' => $spendingStats,
        'available_cards' => $availableCards,
        'reward_programs' => $rewardPrograms
      ];

      // Enviar para a OpenAI para análise
      return $this->analyzeWithOpenAI($analysisData);

    } catch (\Exception $e) {
      Log::error('Erro ao gerar recomendações de cartões', [
        'user_id' => $userId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      // Retornar recomendações genéricas em caso de falha
      return [
        'recommendations' => $this->getFallbackRecommendations(),
        'error' => 'Não foi possível gerar recomendações personalizadas. Usando recomendações genéricas.'
      ];
    }
  }

  /**
   * Analisa transações específicas e sugere cartões melhores para determinados estabelecimentos
   * @param string $userId ID do usuário
   * @return array Recomendações de otimização
   */
  public function getTransactionOptimizationRecommendations(string $userId): array
  {
    try {
      // 1. Obter transações recentes (últimos 30 dias)
      $recentTransactions = Transaction::join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
        ->where('invoices.user_id', $userId)
        ->where('transaction_date', '>=', now()->subDays(30))
        ->with('category')
        ->orderBy('amount', 'desc')
        ->limit(20)
        ->get()
        ->toArray();

      if (empty($recentTransactions)) {
        return [
          'recommendations' => [],
          'message' => 'Adicione faturas recentes para receber recomendações de otimização.'
        ];
      }

      // 2. Obter cartões atuais do usuário
      $userCards = Card::where('user_id', $userId)
        ->where('active', true)
        ->get()
        ->toArray();

      // 3. Obter categorias para contextualização
      $categories = Category::all()->toArray();

      // Preparar os dados para a análise
      $analysisData = [
        'user_cards' => $userCards,
        'recent_transactions' => $recentTransactions,
        'categories' => $categories
      ];

      // Enviar para a OpenAI para análise
      return $this->analyzeTransactionsWithOpenAI($analysisData);

    } catch (\Exception $e) {
      Log::error('Erro ao gerar recomendações de otimização de transações', [
        'user_id' => $userId,
        'error' => $e->getMessage()
      ]);

      return [
        'recommendations' => [],
        'error' => 'Não foi possível analisar suas transações neste momento.'
      ];
    }
  }

  /**
   * Obter as principais categorias de gastos do usuário
   */
  private function getTopSpendingCategories(string $userId, int $months = 3, int $limit = 5): array
  {
    return Transaction::select('categories.id', 'categories.name', 'categories.icon', 'categories.color')
      ->selectRaw('SUM(transactions.amount) as total')
      ->selectRaw('COUNT(transactions.id) as count')
      ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
      ->join('categories', 'transactions.category_id', '=', 'categories.id')
      ->where('invoices.user_id', $userId)
      ->where('transaction_date', '>=', now()->subMonths($months))
      ->groupBy('categories.id', 'categories.name', 'categories.icon', 'categories.color')
      ->orderBy('total', 'desc')
      ->limit($limit)
      ->get()
      ->toArray();
  }

  /**
   * Obter os principais estabelecimentos frequentados pelo usuário
   */
  private function getTopMerchants(string $userId, int $months = 3, int $limit = 10): array
  {
    return Transaction::select('merchant_name')
      ->selectRaw('SUM(amount) as total')
      ->selectRaw('COUNT(*) as frequency')
      ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
      ->where('invoices.user_id', $userId)
      ->where('transaction_date', '>=', now()->subMonths($months))
      ->groupBy('merchant_name')
      ->orderBy('total', 'desc')
      ->limit($limit)
      ->get()
      ->toArray();
  }

  /**
   * Obter estatísticas de gastos mensais
   */
  private function getSpendingStatistics(string $userId): array
  {
    // Gastos totais (últimos 6 meses)
    $monthlySpending = Transaction::selectRaw('DATE_FORMAT(transaction_date, "%Y-%m") as month')
      ->selectRaw('SUM(amount) as total')
      ->join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
      ->where('invoices.user_id', $userId)
      ->where('transaction_date', '>=', now()->subMonths(6))
      ->groupBy('month')
      ->orderBy('month')
      ->get()
      ->toArray();

    // Média mensal de gastos
    $averageMonthlySpend = Transaction::join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
      ->where('invoices.user_id', $userId)
      ->where('transaction_date', '>=', now()->subMonths(6))
      ->avg('amount');

    return [
      'monthly_spending' => $monthlySpending,
      'average_monthly_spend' => $averageMonthlySpend,
      'total_analyzed_period' => Transaction::join('invoices', 'transactions.invoice_id', '=', 'invoices.id')
        ->where('invoices.user_id', $userId)
        ->where('transaction_date', '>=', now()->subMonths(6))
        ->sum('amount')
    ];
  }

  /**
   * Obter cartões disponíveis no sistema que o usuário não possui
   */
  private function getAvailableCards(string $userId): array
  {
    // Obter IDs dos cartões que o usuário já possui
    $userCardIds = Card::where('user_id', $userId)->pluck('id')->toArray();

    // Aqui você normalmente buscaria uma tabela de cartões disponíveis
    // Como é apenas um exemplo, vamos criar alguns cartões fictícios
    return [
      [
        'id' => 'card_platinum',
        'name' => 'Cartão Platinum',
        'bank' => 'Banco XYZ',
        'annual_fee' => 49900, // 499.00
        'conversion_rate' => 2.5,
        'benefits' => 'Oferece 2.5x pontos em restaurantes e supermercados, sala VIP em aeroportos',
        'categories_bonus' => ['FOOD', 'SUPER', 'TRAVEL']
      ],
      [
        'id' => 'card_black',
        'name' => 'Cartão Black',
        'bank' => 'Banco ABC',
        'annual_fee' => 89900, // 899.00
        'conversion_rate' => 3.0,
        'benefits' => 'Oferece 3x pontos em combustível e farmácias, seguro viagem premium',
        'categories_bonus' => ['FUEL', 'PHARM', 'TRAVEL']
      ],
      [
        'id' => 'card_infinite',
        'name' => 'Cartão Infinite',
        'bank' => 'Banco DEF',
        'annual_fee' => 129900, // 1299.00
        'conversion_rate' => 4.0,
        'benefits' => 'Oferece 4x pontos em qualquer categoria, concierge 24h',
        'categories_bonus' => ['FOOD', 'SUPER', 'FUEL', 'TRAVEL', 'PHARM']
      ]
    ];
  }

  /**
   * Analisa os dados com a OpenAI para gerar recomendações
   */
  private function analyzeWithOpenAI(array $analysisData): array
  {
    Log::debug('Enviando dados para análise com OpenAI', [
      'data_size' => strlen(json_encode($analysisData))
    ]);

    $systemPrompt = "Você é um especialista em cartões de crédito e programas de pontos no Brasil. " .
      "Analise os dados do usuário (cartões atuais, categorias de gastos, estabelecimentos frequentes) " .
      "e recomende até 3 cartões que maximizariam os pontos/benefícios com base no perfil de gastos. " .
      "Para cada recomendação, explique por que esse cartão é adequado, quanto o usuário ganharia a mais em pontos " .
      "(estimativa em %) e uma análise de custo-benefício considerando a anuidade. " .
      "Retorne os resultados em formato JSON com pelo menos estes campos: recommendations (array com card_name, description, annual_fee, potential_points_increase, analysis), " .
      "summary (texto curto com análise geral) e action_items (array com ações recomendadas).";

    try {
      $response = Http::timeout(60)->withHeaders([
        'Authorization' => 'Bearer ' . $this->apiKey,
        'Content-Type' => 'application/json',
      ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'messages' => [
              [
                'role' => 'system',
                'content' => $systemPrompt
              ],
              [
                'role' => 'user',
                'content' => "Analise estes dados de usuário e forneça recomendações detalhadas de cartões: " . json_encode($analysisData)
              ]
            ],
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 2000,
            'temperature' => 0.2
          ]);

      if ($response->failed()) {
        Log::error('Erro na API da OpenAI durante análise de recomendações', [
          'status' => $response->status(),
          'body' => $response->body()
        ]);
        throw new \Exception('Erro ao processar recomendações: ' . ($response->json()['error']['message'] ?? 'Erro desconhecido'));
      }

      $responseData = $response->json();
      $content = $responseData['choices'][0]['message']['content'] ?? '';

      // Parse do JSON
      $recommendations = json_decode($content, true);

      if (!is_array($recommendations)) {
        throw new \Exception('Formato de resposta inválido');
      }

      return $recommendations;

    } catch (\Exception $e) {
      Log::error('Erro ao analisar dados com OpenAI', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
      throw $e;
    }
  }

  /**
   * Analisa transações específicas com a OpenAI para gerar recomendações de otimização
   */
  private function analyzeTransactionsWithOpenAI(array $analysisData): array
  {
    Log::debug('Enviando transações para análise com OpenAI', [
      'transaction_count' => count($analysisData['recent_transactions']),
      'data_size' => strlen(json_encode($analysisData))
    ]);

    $systemPrompt = "Você é um especialista em otimização de cartões de crédito e programas de pontos no Brasil. " .
      "Analise as transações recentes do usuário e seus cartões atuais. " .
      "Identifique até 5 transações específicas onde o usuário poderia ter ganho mais pontos usando um cartão diferente. " .
      "Para cada transação, indique qual cartão seria melhor, quantos pontos adicionais o usuário ganharia, e por quê. " .
      "Retorne os resultados em formato JSON com estes campos: optimizations (array com transaction_details, current_card, recommended_card, potential_increase_percentage, reason), " .
      "summary (resumo da análise) e estimated_monthly_point_increase (estimativa de aumento mensal de pontos).";

    try {
      $response = Http::timeout(45)->withHeaders([
        'Authorization' => 'Bearer ' . $this->apiKey,
        'Content-Type' => 'application/json',
      ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'messages' => [
              [
                'role' => 'system',
                'content' => $systemPrompt
              ],
              [
                'role' => 'user',
                'content' => "Analise estas transações e cartões do usuário para otimização de pontos: " . json_encode($analysisData)
              ]
            ],
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 1500,
            'temperature' => 0.2
          ]);

      if ($response->failed()) {
        Log::error('Erro na API da OpenAI durante análise de otimização', [
          'status' => $response->status(),
          'body' => $response->body()
        ]);
        throw new \Exception('Erro ao processar otimizações: ' . ($response->json()['error']['message'] ?? 'Erro desconhecido'));
      }

      $responseData = $response->json();
      $content = $responseData['choices'][0]['message']['content'] ?? '';

      // Parse do JSON
      $optimizations = json_decode($content, true);

      if (!is_array($optimizations)) {
        throw new \Exception('Formato de resposta inválido');
      }

      return $optimizations;

    } catch (\Exception $e) {
      Log::error('Erro ao analisar transações com OpenAI', [
        'error' => $e->getMessage()
      ]);
      throw $e;
    }
  }

  /**
   * Recomendações genéricas em caso de falha
   */
  private function getFallbackRecommendations(): array
  {
    return [
      [
        'card_name' => 'Cartão Platinum',
        'description' => 'Oferece 2.5x pontos em restaurantes e supermercados',
        'annual_fee' => 49900, // 499.00
        'potential_points_increase' => '20%',
        'analysis' => 'Bom para quem gasta em alimentação'
      ],
      [
        'card_name' => 'Cartão Infinite',
        'description' => 'Oferece 3x pontos em combustível e farmácias',
        'annual_fee' => 89900, // 899.00
        'potential_points_increase' => '25%',
        'analysis' => 'Ideal para quem tem gastos frequentes com combustível'
      ]
    ];
  }
}