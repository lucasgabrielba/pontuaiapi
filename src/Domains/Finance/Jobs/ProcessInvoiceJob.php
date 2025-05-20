<?php

namespace Domains\Finance\Jobs;

use Domains\Finance\Contracts\InvoiceProcessorInterface;
use Domains\Finance\Models\Invoice;
use Domains\Finance\Models\Transaction;
use Domains\Finance\Models\Category;
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

    // Configurar timeout para 5 minutos (300 segundos)
    public $timeout = 300;
    public $tries = 3;
    public $backoff = 10;

    protected string $invoiceId;
    protected string $filePath;

    public function __construct(string $invoiceId, string $filePath)
    {
        $this->invoiceId = $invoiceId;
        $this->filePath = $filePath;
        Log::info('Job ProcessInvoiceJob criado', [
            'invoice_id' => $invoiceId,
            'file_path' => $filePath
        ]);
    }

    public function handle(InvoiceProcessorInterface $invoiceProcessor)
    {
        Log::info('Iniciando processamento da fatura', [
            'invoice_id' => $this->invoiceId,
            'file_path' => $this->filePath
        ]);
        
        try {
            $invoice = Invoice::findOrFail($this->invoiceId);
            Log::debug('Fatura encontrada', ['invoice' => $invoice->toArray()]);
            
            if (!Storage::disk('s3')->exists($this->filePath)) {
                Log::error('Arquivo da fatura não encontrado', [
                    'file_path' => $this->filePath
                ]);
                throw new HttpException(404, 'Arquivo da fatura não encontrado');
            }
            
            $fileExtension = pathinfo($this->filePath, PATHINFO_EXTENSION);
            Log::debug('Tipo de arquivo identificado', ['extension' => $fileExtension]);
            
            Log::info('Iniciando processamento do arquivo com processador', [
                'processor' => get_class($invoiceProcessor),
                'file_extension' => $fileExtension
            ]);
            $transactions = $invoiceProcessor->processInvoice($this->filePath, $fileExtension);
            Log::info('Arquivo processado com sucesso', [
                'transaction_count' => count($transactions)
            ]);
            
            $totalAmount = collect($transactions)->sum('amount');
            Log::debug('Valor total calculado', ['total_amount' => $totalAmount]);
            
            $invoice->update([
                'total_amount' => $totalAmount,
                'status' => 'Analisado',
                'due_date' => now()->addDays(15), // Simula uma data de vencimento
                'closing_date' => now()->subDays(5), // Simula uma data de fechamento
            ]);
            Log::info('Fatura atualizada com sucesso', [
                'invoice_id' => $invoice->id,
                'total_amount' => $totalAmount,
                'status' => 'Analisado'
            ]);
            
            // Cria as transações na base de dados
            Log::debug('Iniciando criação das transações na base de dados');
            foreach ($transactions as $index => $transaction) {
                // Se tiver category_code, procura a categoria
                $categoryId = null;
                if (isset($transaction['category_code'])) {
                    $category = Category::where('code', $transaction['category_code'])->first();
                    if ($category) {
                        $categoryId = $category->id;
                        Log::debug('Categoria encontrada', [
                            'category_code' => $transaction['category_code'],
                            'category_id' => $categoryId
                        ]);
                    } else {
                        Log::warning('Categoria não encontrada', [
                            'category_code' => $transaction['category_code']
                        ]);
                    }
                    unset($transaction['category_code']);
                }
                
                $points = $this->calculatePoints($transaction['amount'], $invoice);
                
                $newTransaction = Transaction::create([
                    'invoice_id' => $invoice->id,
                    'merchant_name' => $transaction['merchant_name'],
                    'transaction_date' => $transaction['transaction_date'],
                    'amount' => $transaction['amount'],
                    'description' => $transaction['description'] ?? null,
                    'category_id' => $categoryId,
                    'points_earned' => $points,
                ]);
                
                Log::debug('Transação criada', [
                    'index' => $index + 1,
                    'transaction_id' => $newTransaction->id,
                    'merchant' => $transaction['merchant_name'],
                    'amount' => $transaction['amount'],
                    'points' => $points
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
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Atualiza a fatura com status de erro
            Invoice::where('id', $this->invoiceId)->update([
                'status' => 'Erro'
            ]);
            
            Log::info('Status da fatura atualizado para Erro', [
                'invoice_id' => $this->invoiceId
            ]);
            
            throw $e;
        }
    }
    
    private function calculatePoints(int $amount, Invoice $invoice): int
    {
        // Recupera a taxa de conversão do cartão
        $card = $invoice->card;
        $conversionRate = $card->conversion_rate ?? 1.0;
        
        // Calcula pontos baseado na taxa de conversão do cartão
        // Exemplo: R$ 100 com taxa 1.5 = 150 pontos
        $points = (int)(($amount / 100) * $conversionRate);
        
        Log::debug('Pontos calculados', [
            'amount' => $amount,
            'conversion_rate' => $conversionRate,
            'points' => $points,
            'card_id' => $card->id ?? 'N/A'
        ]);
        
        return $points;
    }
    
    public function failed(\Throwable $exception)
    {
        // Quando o job falhar definitivamente, o status da fatura é atualizado
        try {
            Invoice::where('id', $this->invoiceId)->update([
                'status' => 'Erro',
                'error_message' => substr($exception->getMessage(), 0, 255)
            ]);
            
            Log::error('Job ProcessInvoiceJob falhou definitivamente', [
                'invoice_id' => $this->invoiceId,
                'error' => $exception->getMessage()
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar fatura após falha do job', [
                'invoice_id' => $this->invoiceId,
                'error' => $e->getMessage()
            ]);
        }
    }
}