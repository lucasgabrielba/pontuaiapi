<?php

namespace Domains\Finance\Jobs;

use Domains\Finance\Models\Invoice;
use Domains\Finance\Models\Transaction;
use Domains\Finance\Services\MockAIProcessorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProcessInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $invoiceId;
    protected string $filePath;

    /**
     * Create a new job instance.
     */
    public function __construct(string $invoiceId, string $filePath)
    {
        $this->invoiceId = $invoiceId;
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            // Recupera a fatura
            $invoice = Invoice::findOrFail($this->invoiceId);
            
            // Verifica se o arquivo existe
            if (!Storage::disk('s3')->exists($this->filePath)) {
                throw new HttpException(404, 'Arquivo da fatura não encontrado');
            }
            
            // Recupera o conteúdo do arquivo para enviar para processamento
            $fileContent = Storage::disk('s3')->get($this->filePath);
            $fileExtension = pathinfo($this->filePath, PATHINFO_EXTENSION);
            
            // Aqui você enviaria o arquivo para um serviço de IA para processamento
            // Por enquanto, vamos simular o resultado do processamento baseado no tipo de arquivo
            $transactions = $this->processWithAI($fileContent, $fileExtension, $invoice);
            
            // Atualiza a fatura com os dados processados
            $totalAmount = collect($transactions)->sum('amount');
            
            $invoice->update([
                'total_amount' => $totalAmount,
                'status' => 'Pendente',
                'due_date' => now()->addDays(15), // Simula uma data de vencimento
                'closing_date' => now()->subDays(5), // Simula uma data de fechamento
            ]);
            
            // Cria as transações na base de dados
            foreach ($transactions as $transaction) {
                Transaction::create([
                    'invoice_id' => $invoice->id,
                    'merchant_name' => $transaction['merchant_name'],
                    'transaction_date' => $transaction['transaction_date'],
                    'amount' => $transaction['amount'],
                    'description' => $transaction['description'] ?? null,
                    'category_id' => $transaction['category_id'] ?? null,
                    'points_earned' => $this->calculatePoints($transaction['amount'], $invoice),
                ]);
            }
            
            Log::info('Fatura processada com sucesso', [
                'invoice_id' => $invoice->id,
                'transaction_count' => count($transactions),
                'total_amount' => $totalAmount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao processar fatura', [
                'invoice_id' => $this->invoiceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Atualiza a fatura com status de erro
            Invoice::where('id', $this->invoiceId)->update([
                'status' => 'Erro'
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Processa a fatura usando o serviço de IA
     */
    private function processWithAI(string $fileContent, string $fileExtension, Invoice $invoice): array
    {
        // Instancia o serviço de processamento (em produção seria injetado via DI)
        $aiProcessor = new MockAIProcessorService();
        
        try {
            // Processa o arquivo usando o serviço de IA
            $transactions = $aiProcessor->processInvoice($this->filePath, $fileExtension);
            
            // Para cada transação, busca a categoria pelo código se disponível
            foreach ($transactions as &$transaction) {
                if (isset($transaction['category_code'])) {
                    $category = \Domains\Finance\Models\Category::where('code', $transaction['category_code'])->first();
                    if ($category) {
                        $transaction['category_id'] = $category->id;
                    }
                    unset($transaction['category_code']);
                }
            }
            
            return $transactions;
        } catch (\Exception $e) {
            Log::error('Erro no processamento de IA', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            
            // Fallback para simulação básica em caso de erro
            if ($fileExtension === 'csv') {
                return $this->processCsvContent($fileContent);
            } elseif (in_array($fileExtension, ['pdf', 'jpg', 'jpeg', 'png'])) {
                return $this->simulateAIResponse($invoice);
            }
            
            throw new HttpException(400, 'Formato de arquivo não suportado para processamento');
        }
    }
    
    /**
     * Processa o conteúdo CSV para extrair transações
     */
    private function processCsvContent(string $content): array
    {
        $rows = array_map('str_getcsv', explode("\n", $content));
        
        // Remove a linha de cabeçalho
        $header = array_shift($rows);
        
        $transactions = [];
        
        foreach ($rows as $row) {
            if (count($row) >= 3) { // Validação básica
                $transactions[] = [
                    'merchant_name' => $row[0],
                    'transaction_date' => date('Y-m-d', strtotime($row[1])),
                    'amount' => (int)($row[2] * 100), // Converte para centavos
                    'description' => $row[3] ?? null,
                ];
            }
        }
        
        return $transactions;
    }
    
    /**
     * Simula o resultado de uma análise de IA para arquivos não-CSV
     */
    private function simulateAIResponse(Invoice $invoice): array
    {
        // Lista de estabelecimentos fictícios para simulação
        $merchants = [
            'Supermercado Extra' => ['category' => 'Supermercado', 'code' => 'SUPER'],
            'Netflix' => ['category' => 'Streaming', 'code' => 'STREAM'],
            'Amazon' => ['category' => 'Compras Online', 'code' => 'ECOMM'],
            'Posto Ipiranga' => ['category' => 'Combustível', 'code' => 'FUEL'],
            'Restaurante Outback' => ['category' => 'Restaurante', 'code' => 'RESTA'],
            'Farmácia Droga Raia' => ['category' => 'Farmácia', 'code' => 'PHARM'],
            'Uber' => ['category' => 'Transporte', 'code' => 'TRANS'],
            'iFood' => ['category' => 'Delivery', 'code' => 'DELIV']
        ];
        
        $transactions = [];
        
        // Gera de 5 a 10 transações aleatórias
        $count = rand(5, 10);
        for ($i = 0; $i < $count; $i++) {
            $merchantName = array_rand($merchants);
            $amount = rand(1000, 50000); // 10-500 reais em centavos
            
            $transactions[] = [
                'merchant_name' => $merchantName,
                'transaction_date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
                'amount' => $amount,
                'description' => $merchants[$merchantName]['category'] . ' - Transação simulada',
                'category_code' => $merchants[$merchantName]['code'],
            ];
        }
        
        return $transactions;
    }
    
    /**
     * Calcula os pontos ganhos em uma transação
     */
    private function calculatePoints(int $amount, Invoice $invoice): int
    {
        // Recupera a taxa de conversão do cartão
        $card = $invoice->card;
        $conversionRate = $card->conversion_rate ?? 1.0;
        
        // Calcula pontos baseado na taxa de conversão do cartão
        // Exemplo: R$ 100 com taxa 1.5 = 150 pontos
        return (int)(($amount / 100) * $conversionRate);
    }
}