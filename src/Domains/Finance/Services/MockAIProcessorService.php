<?php

namespace Domains\Finance\Services;

use Domains\Finance\Contracts\InvoiceProcessorInterface;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MockAIProcessorService implements InvoiceProcessorInterface
{
    /**
     * Simula o processamento de um arquivo de fatura
     */
    public function processInvoice(string $filePath, string $fileType): array
    {
        // Verificar se o arquivo existe
        if (!Storage::disk('s3')->exists($filePath)) {
            throw new HttpException(404, 'Arquivo não encontrado');
        }
        
        // Processar de acordo com o tipo de arquivo
        if ($fileType === 'csv') {
            // Lê o conteúdo do CSV
            $content = Storage::disk('s3')->get($filePath);
            return $this->processCsvContent($content);
        } else {
            // Simula extração de dados para arquivos binários (PDF, imagens)
            return $this->simulateAIExtraction();
        }
    }
    
    /**
     * Simula a categorização de uma transação
     */
    public function categorizeTransaction(string $merchantName): ?string
    {
        // Mapeamento simulado de estabelecimentos para categorias
        $categoryMap = [
            'mercado' => 'SUPER',
            'supermercado' => 'SUPER',
            'netflix' => 'STREAM',
            'spotify' => 'STREAM',
            'amazon' => 'ECOMM',
            'posto' => 'FUEL',
            'combustível' => 'FUEL',
            'gasolina' => 'FUEL',
            'restaurante' => 'RESTA',
            'bar' => 'RESTA',
            'farmácia' => 'PHARM',
            'drogaria' => 'PHARM',
            'uber' => 'TRANS',
            '99' => 'TRANS',
            'taxi' => 'TRANS',
            'ifood' => 'DELIV',
            'rappi' => 'DELIV',
        ];
        
        // Converte para minúsculas para comparação
        $merchantLower = strtolower($merchantName);
        
        // Verifica se o nome do estabelecimento contém alguma palavra-chave
        foreach ($categoryMap as $keyword => $categoryCode) {
            if (strpos($merchantLower, $keyword) !== false) {
                return $categoryCode;
            }
        }
        
        // Se não encontrou correspondência
        return null;
    }
    
    /**
     * Simula a sugestão de melhor cartão para um estabelecimento
     */
    public function suggestBestCard(string $merchantName, array $userCards): array
    {
        // Em um cenário real, aqui teríamos um modelo de IA
        // que consideraria padrões históricos, benefícios específicos, etc.
        
        // Para simulação, apenas retorna o cartão com maior taxa de conversão
        if (empty($userCards)) {
            return [
                'success' => false,
                'message' => 'Nenhum cartão disponível para análise'
            ];
        }
        
        // Encontra o cartão com maior taxa de conversão
        $bestCard = null;
        $highestRate = 0;
        
        foreach ($userCards as $card) {
            if ($card['conversion_rate'] > $highestRate) {
                $bestCard = $card;
                $highestRate = $card['conversion_rate'];
            }
        }
        
        return [
            'success' => true,
            'message' => 'Encontramos uma sugestão de cartão para este estabelecimento',
            'card' => $bestCard,
            'reason' => 'Este cartão oferece a melhor taxa de conversão para pontos',
            'estimated_points' => 'Para cada R$ 1,00 gasto, você receberá ' . $highestRate . ' pontos'
        ];
    }
    
    /**
     * Processa o conteúdo de um arquivo CSV
     */
    private function processCsvContent(string $content): array
    {
        $rows = array_map('str_getcsv', explode("\n", $content));
        
        // Remove a linha de cabeçalho
        $header = array_shift($rows);
        
        $transactions = [];
        
        foreach ($rows as $row) {
            if (count($row) >= 3) { // Validação básica
                $merchantName = $row[0];
                
                // Tenta categorizar a transação
                $categoryCode = $this->categorizeTransaction($merchantName);
                
                $transactions[] = [
                    'merchant_name' => $merchantName,
                    'transaction_date' => date('Y-m-d', strtotime($row[1])),
                    'amount' => (int)($row[2] * 100), // Converte para centavos
                    'description' => $row[3] ?? null,
                    'category_code' => $categoryCode,
                ];
            }
        }
        
        return $transactions;
    }
    
    /**
     * Simula a extração de dados por IA de arquivos PDF/imagens
     */
    private function simulateAIExtraction(): array
    {
        // Lista de estabelecimentos fictícios para simulação
        $merchants = [
            'Supermercado Extra',
            'Netflix',
            'Amazon',
            'Posto Ipiranga',
            'Restaurante Outback',
            'Farmácia Droga Raia',
            'Uber',
            'iFood'
        ];
        
        $transactions = [];
        
        // Gera de 5 a 10 transações aleatórias
        $count = rand(5, 10);
        for ($i = 0; $i < $count; $i++) {
            $merchantName = $merchants[array_rand($merchants)];
            $amount = rand(1000, 50000); // 10-500 reais em centavos
            
            // Tenta categorizar a transação
            $categoryCode = $this->categorizeTransaction($merchantName);
            
            $transactions[] = [
                'merchant_name' => $merchantName,
                'transaction_date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
                'amount' => $amount,
                'description' => 'Transação extraída por AI',
                'category_code' => $categoryCode,
            ];
        }
        
        return $transactions;
    }
}