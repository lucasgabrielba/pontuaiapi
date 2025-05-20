<?php

namespace Domains\Finance\Services;

use Domains\Finance\Contracts\InvoiceProcessorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Smalot\PdfParser\Parser as PdfParser;
use Carbon\Carbon;

class OpenAIProcessorService implements InvoiceProcessorInterface
{
    protected string $apiKey;
    protected string $visionModel;
    protected string $documentModel;
    protected string $categorizationModel;
    protected array $categoryPatterns = [
        'FOOD' => ['alimentacao', 'restaurante', 'lanchonete', 'burger', 'pizza', 'lanche', 'spoleto', 'outback', 'kfc', 'mcdonalds', 'comida'],
        'SUPER' => ['supermercado', 'mercado', 'bem mais', 'assai', 'atacad', 'extra', 'carrefour', 'pao de acucar', 'mercearia', 'hortifruti'],
        'TRANS' => ['uber', 'trip', '99', 'taxi', 'onibus', 'pedágio', 'transporte', 'passagem', 'viagem', 'gotogate', 'metro', 'trem'],
        'FUEL' => ['posto', 'combustivel', 'gasolina', 'etanol', 'gas', 'ipiranga', 'shell', 'petrobras', 'alcool'],
        'STREAM' => ['netflix', 'spotify', 'youtube', 'stream', 'prime', 'disney', 'hbo', 'max', 'paramount', 'deezer', 'apple music'],
        'PHARM' => ['farmacia', 'drogaria', 'drogas', 'pague menos', 'drogasil', 'pharma', 'remedio', 'medicamento'],
        'ECOMM' => ['amazon', 'e-commerce', 'marketplace', 'shop', 'loja virtual', 'magazine', 'americanas', 'submarino', 'mercado livre', 'shopee'],
        'DELIV' => ['ifood', 'delivery', 'entrega', 'rappi', 'james', 'aiqfome', 'uber eats'],
        'EDU' => ['educacao', 'curso', 'livro', 'material escolar', 'escola', 'faculdade', 'universidade', 'livraria', 'alura', 'udemy'],
        'HEALTH' => ['saude', 'medico', 'exame', 'plano de saude', 'clinica', 'hospital', 'consulta', 'dentista', 'unimed', 'amil', 'hapvida'],
        'LEISURE' => ['lazer', 'cinema', 'teatro', 'show', 'evento', 'ingresso', 'cinemark', 'cinepolis', 'museu', 'parque'],
        'TRAVEL' => ['viagem', 'hotel', 'hostel', 'pousada', 'passagem', 'aerea', 'voo', 'pacote', 'booking', 'decolar', 'cvc', 'airbnb', 'latam', 'gol'],
        'CLOTH' => ['vestuario', 'roupa', 'calcado', 'tenis', 'sapato', 'acessorio', 'moda', 'zara', 'renner', 'riachuelo', 'c&a', 'hering'],
        'SUBS' => ['assinatura', 'mensalidade', 'plano', 'academia', 'clube', 'subscription', 'recorrente', 'revista', 'jornal'],
        'HOME' => ['casa', 'aluguel', 'condominio', 'luz', 'energia', 'agua', 'internet', 'fibra', 'movel', 'eletrodomestico', 'reforma'],
        'FUN' => ['diversao', 'bar', 'balada', 'festa', 'pub', 'cerveja', 'bebida', 'boate', 'clube', 'happy hour'],
        'PIX' => ['pix', 'transferencia', 'ted', 'doc', 'pagamento'],
        'OTHER' => ['outro', 'diversos', 'geral', 'variados']
    ];

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
        $this->visionModel = config('services.openai.vision_model', 'gpt-4o-mini');
        $this->documentModel = config('services.openai.document_model', 'gpt-3.5-turbo');
        $this->categorizationModel = config('services.openai.categorization_model', 'gpt-3.5-turbo');

        if (!$this->apiKey) {
            Log::error('API Key da OpenAI não configurada');
            throw new \Exception('API Key da OpenAI não configurada');
        }

        Log::debug('OpenAIProcessorService inicializado', [
            'vision_model' => $this->visionModel,
            'document_model' => $this->documentModel,
            'categorization_model' => $this->categorizationModel
        ]);
    }

    public function processInvoice(string $filePath, string $fileType): array
    {
        Log::info('Iniciando processamento de fatura', [
            'file_path' => $filePath,
            'file_type' => $fileType
        ]);

        // Verificar se o arquivo existe
        if (!Storage::disk('s3')->exists($filePath)) {
            Log::error('Arquivo não encontrado', ['file_path' => $filePath]);
            throw new HttpException(404, 'Arquivo não encontrado');
        }

        // Obtém o conteúdo do arquivo
        $fileContent = Storage::disk('s3')->get($filePath);
        Log::debug('Arquivo carregado com sucesso', [
            'size' => strlen($fileContent),
            'file_path' => $filePath
        ]);

        // Para PDFs, usar diretamente a extração de texto sem tentar visão
        if ($fileType === 'pdf') {
            Log::info('Processando PDF com extração de texto');
            return $this->processWithTextExtraction($fileContent, $filePath);
        }

        // Para imagens, usar a API de visão
        if (in_array($fileType, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            Log::info('Processando arquivo visual', ['file_type' => $fileType]);
            return $this->processWithVision($fileContent, $fileType, $filePath);
        }

        // Para CSVs, é mais eficiente tratar sem a API
        if ($fileType === 'csv') {
            Log::info('Processando arquivo CSV');
            return $this->processCsvContent($fileContent);
        }

        Log::error('Formato de arquivo não suportado', ['file_type' => $fileType]);
        throw new HttpException(400, 'Formato de arquivo não suportado');
    }

    private function processWithTextExtraction(string $fileContent, string $filePath): array
    {
        try {
            // Salvar o PDF temporariamente
            $tempPdfPath = sys_get_temp_dir() . '/' . basename($filePath);
            file_put_contents($tempPdfPath, $fileContent);

            Log::debug('PDF salvo temporariamente para extração de texto', [
                'temp_path' => $tempPdfPath
            ]);

            // Extrair texto do PDF com tratamento de erros mais robusto
            try {
                $parser = new PdfParser();
                $pdf = $parser->parseFile($tempPdfPath);
                $textContent = $pdf->getText();

                Log::debug('Texto extraído do PDF', [
                    'text_length' => strlen($textContent)
                ]);
            } catch (\Exception $pdfError) {
                Log::error('Erro ao extrair texto do PDF com PdfParser', [
                    'error' => $pdfError->getMessage()
                ]);

                throw new \Exception('Falha na extração de texto do PDF: ' . $pdfError->getMessage());
            }

            // Limpar arquivo temporário
            unlink($tempPdfPath);

            if (empty($textContent)) {
                Log::error('Nenhum texto extraído do PDF', [
                    'file_path' => $filePath
                ]);
                throw new \Exception('Não foi possível extrair texto do PDF');
            }

            // Nova abordagem: Primeiro tentar extrair com heurística (padrões de extrato)
            try {
                $transactions = $this->extractTransactionsWithHeuristics($textContent);
                if (!empty($transactions)) {
                    Log::info('Transações extraídas usando heurística', [
                        'count' => count($transactions)
                    ]);
                    return $transactions;
                }

                Log::warning('Nenhuma transação encontrada com heurística, tentando processamento com IA');
            } catch (\Exception $e) {
                Log::warning('Erro ao processar com heurística, tentando IA', [
                    'error' => $e->getMessage()
                ]);
            }

            // Teste se é um banco conhecido
            $bankType = $this->identifyBankType($textContent);
            if ($bankType) {
                Log::info('Banco identificado', ['bank' => $bankType]);
                $transactions = $this->processSpecificBankFormat($textContent, $bankType);
                if (!empty($transactions)) {
                    return $transactions;
                }
            }

            // Se não conseguir com heurísticas, continua com o fluxo de IA
            // Abordagem direta: processar todo o texto de uma vez 
            try {
                return $this->processExtractedTextAtOnce($textContent);
            } catch (\Exception $e) {
                Log::warning('Falha ao processar texto completo, tentando abordagem por chunks', [
                    'error' => $e->getMessage()
                ]);

                // Se falhar, tentar a abordagem por chunks
                return $this->processExtractedTextInChunks($textContent);
            }

        } catch (\Exception $e) {
            Log::error('Erro ao extrair e processar texto do PDF', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Erro ao extrair e processar texto do PDF: ' . $e->getMessage());
        }
    }

    /**
     * Identifica o tipo de banco com base no conteúdo do texto
     */
    private function identifyBankType(string $textContent): ?string
    {
        $textContent = strtolower($textContent);

        if (strpos($textContent, 'banco inter') !== false || strpos($textContent, 'inter') !== false) {
            return 'inter';
        }

        if (strpos($textContent, 'nubank') !== false || strpos($textContent, 'nu pagamentos') !== false) {
            return 'nubank';
        }

        if (strpos($textContent, 'itaú') !== false || strpos($textContent, 'itau') !== false) {
            return 'itau';
        }

        if (strpos($textContent, 'bradesco') !== false) {
            return 'bradesco';
        }

        if (strpos($textContent, 'santander') !== false) {
            return 'santander';
        }

        if (strpos($textContent, 'banco do brasil') !== false || strpos($textContent, 'bb') !== false) {
            return 'bb';
        }

        if (strpos($textContent, 'caixa') !== false || strpos($textContent, 'cef') !== false) {
            return 'caixa';
        }

        return null;
    }

    /**
     * Processa formato específico de banco
     */
    private function processSpecificBankFormat(string $textContent, string $bankType): array
    {
        switch ($bankType) {
            case 'inter':
                return $this->processInterBankFormat($textContent);
            // Adicione outros bancos conforme necessário
            default:
                return [];
        }
    }

    /**
     * Processa formato específico do Banco Inter
     */
    private function processInterBankFormat(string $textContent): array
    {
        Log::info('Processando formato específico do Banco Inter');
        $transactions = [];
        $lines = explode("\n", $textContent);

        // Padrão de linha de transação do Inter
        // Exemplo: "Compra no debito: "No estabelecimento SUPERMERCADO BEM MAIS JOAO PESSOA BRA" -R$ 23,03 R$ 259,42"
        $transactionPattern = '/^(Compra.*?|Pix enviado:|Pix recebido:|Boleto recebido).*?"(.*?)".*?([-+]R\$\s*[\d.,]+)/i';

        // Formato de data: "15 de Abril de 2025"
        $datePattern = '/(\d+)\s+de\s+(\w+)\s+de\s+(\d{4})/i';

        $currentDate = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line))
                continue;

            // Verifica se é linha de data
            if (preg_match($datePattern, $line, $dateMatches)) {
                $day = $dateMatches[1];
                $month = $this->getMonthNumber($dateMatches[2]);
                $year = $dateMatches[3];
                $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                Log::debug('Data encontrada', ['date' => $currentDate]);
                continue;
            }

            // Verifica se é linha de transação
            if (preg_match($transactionPattern, $line, $matches)) {
                $transactionType = trim($matches[1]);
                $merchantName = trim($matches[2]);
                $amountText = trim($matches[3]);

                // Se não tiver data atual, pular
                if (!$currentDate)
                    continue;

                // Determinar se é débito ou crédito e processar valor
                $isDebit = strpos($amountText, '-R$') !== false;
                $amountText = str_replace(['-R$', 'R$', '.'], '', $amountText);
                $amountText = str_replace(',', '.', $amountText);
                $amount = (int) (floatval($amountText) * 100);

                // Para transações de débito, tornar o valor negativo
                if ($isDebit) {
                    $amount = -$amount;
                }

                // Categorizar a transação
                $categoryCode = $this->categorizeTransactionHeuristic($merchantName);

                // Determinar descrição
                $description = $transactionType;

                // Criar transação
                $transaction = [
                    'merchant_name' => $merchantName,
                    'transaction_date' => $currentDate,
                    'amount' => $amount,
                    'description' => $description,
                    'category_code' => $categoryCode,
                ];

                Log::debug('Transação extraída do formato Inter', [
                    'merchant' => $merchantName,
                    'date' => $currentDate,
                    'amount' => $amount,
                    'category' => $categoryCode
                ]);

                $transactions[] = $transaction;
            }
        }

        Log::info('Processamento do formato Inter concluído', [
            'transactions_count' => count($transactions)
        ]);

        return $transactions;
    }

    /**
     * Converte nome do mês em português para número
     */
    private function getMonthNumber(string $monthName): int
    {
        $monthName = mb_strtolower($monthName, 'UTF-8');
        $months = [
            'janeiro' => 1,
            'fevereiro' => 2,
            'março' => 3,
            'abril' => 4,
            'maio' => 5,
            'junho' => 6,
            'julho' => 7,
            'agosto' => 8,
            'setembro' => 9,
            'outubro' => 10,
            'novembro' => 11,
            'dezembro' => 12
        ];

        return $months[$monthName] ?? 1;
    }

    /**
     * Extrai transações usando heurísticas baseadas em padrões comuns
     */
    private function extractTransactionsWithHeuristics(string $textContent): array
    {
        Log::info('Tentando extrair transações com heurísticas');
        $transactions = [];
        $lines = explode("\n", $textContent);

        // Padrões comuns para identificar linhas de transação
        $datePattern = '/(\d{2}\/\d{2}\/\d{4}|\d{2}\.\d{2}\.\d{4}|\d{2}-\d{2}-\d{4})/';
        $valuePattern = '/(R\$\s*[\d.,]+|-R\$\s*[\d.,]+|\$\s*[\d.,]+|-\$\s*[\d.,]+)/';

        $currentDate = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line))
                continue;

            // Verificar se linha contém data e valor
            $hasDate = preg_match($datePattern, $line, $dateMatches);
            $hasValue = preg_match($valuePattern, $line, $valueMatches);

            // Linha deve ter data ou valor para ser considerada transação
            if (!$hasValue)
                continue;

            // Extrair data
            if ($hasDate) {
                $dateStr = $dateMatches[1];
                $dateStr = str_replace(['/', '.', '-'], '-', $dateStr);
                try {
                    $date = Carbon::createFromFormat('d-m-Y', $dateStr);
                    $currentDate = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    // Se falhar, tentar usar a data atual
                    $currentDate = $currentDate ?? date('Y-m-d');
                }
            }

            // Extrair valor
            $valueStr = $valueMatches[1];
            $isDebit = strpos($valueStr, '-') !== false;
            $valueStr = str_replace(['R$', '$', '-', '.'], '', $valueStr);
            $valueStr = str_replace(',', '.', $valueStr);
            $amount = (int) (floatval($valueStr) * 100);

            // Para transações de débito, tornar o valor negativo
            if ($isDebit) {
                $amount = -$amount;
            }

            // Extrair nome do estabelecimento (tudo antes do valor ou data)
            $merchantName = $line;
            if ($hasValue) {
                $merchantName = substr($line, 0, strpos($line, $valueMatches[0]));
            }
            if ($hasDate && strpos($merchantName, $dateMatches[0]) !== false) {
                $merchantName = substr($merchantName, 0, strpos($merchantName, $dateMatches[0]));
            }

            $merchantName = trim($merchantName);

            // Se não conseguiu extrair um nome de estabelecimento válido, pular
            if (empty($merchantName) || strlen($merchantName) < 3)
                continue;

            // Categorizar a transação
            $categoryCode = $this->categorizeTransactionHeuristic($merchantName);

            // Criar transação
            $transaction = [
                'merchant_name' => $merchantName,
                'transaction_date' => $currentDate ?? date('Y-m-d'),
                'amount' => $amount,
                'description' => $merchantName,
                'category_code' => $categoryCode,
            ];

            Log::debug('Transação extraída com heurística', [
                'merchant' => $merchantName,
                'date' => $transaction['transaction_date'],
                'amount' => $amount,
                'category' => $categoryCode
            ]);

            $transactions[] = $transaction;
        }

        Log::info('Extração heurística concluída', [
            'transactions_count' => count($transactions)
        ]);

        return $transactions;
    }

    /**
     * Categoriza uma transação usando heurística baseada em padrões
     */
    private function categorizeTransactionHeuristic(string $merchantName): string
    {
        $merchantName = mb_strtolower($merchantName, 'UTF-8');

        foreach ($this->categoryPatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($merchantName, $pattern) !== false) {
                    return $category;
                }
            }
        }

        // Categoria padrão se não encontrar nenhuma correspondência
        return 'OTHER';
    }

    /**
     * Tenta processar todo o texto de uma vez com foco apenas nas transações
     */
    private function processExtractedTextAtOnce(string $textContent): array
    {
        Log::info('Tentando processar todo o texto de uma vez');

        // Extrair apenas as linhas que contêm informações de transações
        $processedText = $this->preprocessTextForTransactions($textContent);

        $prompt = "Este é um texto extraído de uma fatura de cartão de crédito ou extrato bancário. " .
            "Extraia APENAS as transações financeiras. " .
            "Para cada transação, identifique: " .
            "1) Nome do estabelecimento (merchant_name), " .
            "2) Data da transação (transaction_date no formato YYYY-MM-DD), " .
            "3) Valor em centavos (amount como número inteiro, use valores negativos para débitos), " .
            "4) Descrição (description), " .
            "5) Código de categoria (category_code: FOOD, SUPER, TRANS, FUEL, STREAM, PHARM, ECOMM, DELIV, EDU, HEALTH, LEISURE, TRAVEL, CLOTH, SUBS, HOME, FUN, PIX, OTHER). " .
            "Retorne os resultados em formato JSON array.";

        $startTime = microtime(true);
        $response = Http::timeout(60)->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->documentModel,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Você é um especialista em extrair transações de faturas de cartão de crédito e extratos bancários. Extraia APENAS transações financeiras em formato JSON estruturado. Para valores, use centavos (inteiro). Use valores negativos para débitos e positivos para créditos. Categorize cada transação.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt . "\n\n" . $processedText
                        ]
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens' => 2000,
                    'temperature' => 0.1
                ]);
        $duration = microtime(true) - $startTime;

        Log::info('Resposta recebida do processamento completo', [
            'status' => $response->status(),
            'duration' => round($duration, 2) . 's',
            'response_size' => strlen($response->body())
        ]);

        if ($response->failed()) {
            Log::error('Erro na API da OpenAI com processamento completo', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception('Erro ao processar texto: ' . ($response->json()['error']['message'] ?? 'Erro desconhecido'));
        }

        // Parse transactions
        $transactions = $this->parseOpenAIResponse($response->json());

        if (empty($transactions)) {
            throw new \Exception('Nenhuma transação encontrada no processamento completo');
        }

        Log::info('Transações extraídas com processamento completo', [
            'count' => count($transactions)
        ]);

        return $transactions;
    }

    /**
     * Pré-processa o texto para manter apenas linhas relevantes para transações
     */
    private function preprocessTextForTransactions(string $textContent): string
    {
        // Dividir o texto em linhas
        $lines = explode("\n", $textContent);
        $processedLines = [];

        // Palavras-chave comuns que indicam linhas de transação
        $keywords = [
            'R$',
            'BRA',
            'COMPRA',
            'PAGAMENTO',
            'SUPERMERCADO',
            'FARMACIA',
            'UBER',
            'IFOOD',
            'RESTAURANTE',
            'POSTO',
            'COMBUSTIVEL',
            'STREAMING',
            'NETFLIX',
            'AMAZON',
            'SPOTIFY',
            'DEBITO',
            'CREDITO',
            'PIX',
            'TRANSFERENCIA',
            'SAQUE',
            'DEPOSITO',
            'FATURA',
            'ESTABELECIMENTO'
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line))
                continue;

            // Verificar se a linha contém alguma palavra-chave
            $containsKeyword = false;
            foreach ($keywords as $keyword) {
                if (stripos($line, $keyword) !== false) {
                    $containsKeyword = true;
                    break;
                }
            }

            // Verificar se a linha contém um padrão de data (DD/MM ou DD/MM/YYYY)
            $containsDatePattern = preg_match('/\d{2}\/\d{2}(\/\d{4})?/', $line);

            // Verificar se a linha contém um valor monetário (R$ XX,XX)
            $containsMoneyPattern = preg_match('/R\$\s*\d+[,\.]\d{2}/', $line);

            if ($containsKeyword || $containsDatePattern || $containsMoneyPattern) {
                $processedLines[] = $line;
            }
        }

        return implode("\n", $processedLines);
    }

    /**
     * Processa o texto em chunks pequenos
     */
    private function processExtractedTextInChunks(string $textContent): array
    {
        // Dividir o texto em partes ainda menores - reduzindo para 1000 caracteres
        $maxChunkSize = 1000;
        $chunks = $this->chunkText($textContent, $maxChunkSize);

        Log::debug('Texto dividido em ' . count($chunks) . ' partes');

        $allTransactions = [];
        $processingErrors = 0;

        foreach ($chunks as $index => $chunk) {
            Log::debug('Processando parte ' . ($index + 1) . ' de ' . count($chunks), [
                'chunk_size' => strlen($chunk)
            ]);

            // Se já foram obtidas muitas transações ou já houve muitos erros, não continuar
            if (count($allTransactions) > 50 || $processingErrors > 3) {
                Log::info('Interrompendo processamento de chunks: ' .
                    (count($allTransactions) > 50 ? 'transações suficientes obtidas' : 'muitos erros consecutivos'));
                break;
            }

            try {
                // Tenta extrair apenas linhas relevantes do chunk atual
                $processedChunk = $this->preprocessTextForTransactions($chunk);

                // Se o chunk processado estiver vazio, pular para o próximo
                if (empty(trim($processedChunk))) {
                    Log::debug('Chunk ' . ($index + 1) . ' não contém dados relevantes, pulando');
                    continue;
                }

                $response = Http::timeout(45)  // Reduzir timeout para garantir resposta mais rápida
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $this->documentModel,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'Você é um assistente especializado em extrair apenas informações de transações de faturas de cartão de crédito e extratos bancários. Extraia somente: nome do estabelecimento, data, valor em centavos (inteiro, negativo para débitos), e categoria. Retorne os dados em formato JSON. Se não houver transações claras, retorne um array vazio.'
                            ],
                            [
                                'role' => 'user',
                                'content' => "Extraia apenas as transações do texto abaixo, retornando em formato JSON. Use apenas os códigos de categoria: FOOD, SUPER, TRANS, FUEL, STREAM, PHARM, ECOMM, DELIV, EDU, HEALTH, LEISURE, TRAVEL, CLOTH, SUBS, HOME, FUN, PIX, OTHER. Retorne um array vazio se não encontrar transações claras:\n\n$processedChunk"
                            ]
                        ],
                        'response_format' => ['type' => 'json_object'],
                        'max_tokens' => 1000,  // Reduzido para menor tempo de resposta
                        'temperature' => 0.1   // Baixa temperatura para respostas mais consistentes
                    ]);

                if ($response->failed()) {
                    Log::error('Erro na API da OpenAI ao processar chunk ' . ($index + 1), [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    $processingErrors++;
                    continue;
                }

                // Parse transactions from this chunk
                $chunkTransactions = $this->parseOpenAIResponse($response->json());

                // Se não houver transações, continue para o próximo chunk
                if (empty($chunkTransactions)) {
                    Log::info('Nenhuma transação encontrada no chunk ' . ($index + 1));
                    continue;
                }

                $allTransactions = array_merge($allTransactions, $chunkTransactions);
                $processingErrors = 0; // Resetar contador de erros se teve sucesso

                Log::info('Extraídas ' . count($chunkTransactions) . ' transações do chunk ' . ($index + 1));

            } catch (\Exception $apiError) {
                Log::error('Erro ao processar chunk ' . ($index + 1) . ' com API', [
                    'error' => $apiError->getMessage(),
                    'chunk_index' => $index
                ]);
                $processingErrors++;
            }

            // Pequena pausa entre as requisições para evitar rate limiting
            if ($index < count($chunks) - 1) {
                usleep(250000); // 250ms
            }
        }

        Log::info('Processamento de texto concluído. Total de ' . count($allTransactions) . ' transações extraídas');

        if (empty($allTransactions)) {
            Log::warning('Nenhuma transação foi extraída em nenhum dos chunks');

            // Último recurso: tentar com uma abordagem simplificada
            try {
                Log::info('Tentando última abordagem simplificada');
                $simplifiedText = $this->preprocessTextForTransactions($textContent);
                $sampleText = substr($simplifiedText, 0, 4000);

                $response = Http::timeout(45)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $this->documentModel,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'Você é um assistente especializado em extrair apenas transações de texto de faturas e extratos bancários. Identifique estabelecimentos, datas e valores. Retorne os dados em formato JSON. Use valores em centavos (inteiros).'
                            ],
                            [
                                'role' => 'user',
                                'content' => "Extraia qualquer transação que você consiga identificar no texto abaixo, retornando em formato JSON, mesmo que sejam apenas nomes de estabelecimentos e valores. Use apenas os códigos de categoria: FOOD, SUPER, TRANS, FUEL, STREAM, PHARM, ECOMM, DELIV, EDU, HEALTH, LEISURE, TRAVEL, CLOTH, SUBS, HOME, FUN, PIX, OTHER.\n\n$sampleText"
                            ]
                        ],
                        'response_format' => ['type' => 'json_object'],
                        'max_tokens' => 1500
                    ]);

                if (!$response->failed()) {
                    $finalTransactions = $this->parseOpenAIResponse($response->json());
                    if (!empty($finalTransactions)) {
                        Log::info('Última tentativa extraiu ' . count($finalTransactions) . ' transações');
                        $allTransactions = $finalTransactions;
                    }
                }
            } catch (\Exception $e) {
                Log::error('Falha na última tentativa de extrair transações', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $allTransactions;
    }

    /**
     * Divide um texto em partes menores respeitando quebras de linha quando possível
     */
    private function chunkText(string $text, int $maxSize): array
    {
        $chunks = [];
        $textLength = strlen($text);

        if ($textLength <= $maxSize) {
            return [$text];
        }

        $start = 0;

        while ($start < $textLength) {
            $size = min($maxSize, $textLength - $start);

            if ($start + $size < $textLength) {
                // Procurar pelo último caractere de quebra de linha dentro do tamanho máximo
                $breakPos = strrpos(substr($text, $start, $size), "\n");

                if ($breakPos !== false) {
                    $size = $breakPos + 1; // +1 para incluir o caractere de quebra
                } else {
                    // Se não encontrar quebra de linha, procure pelo último espaço
                    $spacePos = strrpos(substr($text, $start, $size), " ");
                    if ($spacePos !== false) {
                        $size = $spacePos + 1; // +1 para incluir o espaço
                    }
                }
            }

            $chunks[] = substr($text, $start, $size);
            $start += $size;
        }

        return $chunks;
    }

    /**
     * Processa o arquivo usando URL temporária do S3
     */
    private function processWithS3PresignedUrl(string $filePath, string $fileType): array
    {
        // Verificar se o arquivo é de um tipo de imagem suportado
        if (!in_array($fileType, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            throw new \Exception("Formato de arquivo não suportado para processamento de visão: $fileType");
        }

        // Gerar URL temporária do S3 com duração de 5 minutos
        $tempUrl = Storage::disk('s3')->temporaryUrl(
            $filePath,
            now()->addMinutes(5)
        );

        Log::debug('URL temporária do S3 gerada', [
            'url' => $tempUrl,
            'file_path' => $filePath,
            'expires' => now()->addMinutes(5)->toIso8601String()
        ]);

        // Prepara o prompt para a API usando JSON mode para melhor estruturação
        $prompt = "Analise esta fatura de cartão de crédito ou extrato bancário e extraia todas as transações no formato JSON.";

        Log::debug('Enviando requisição para a API com URL temporária do S3', [
            'model' => $this->visionModel,
            'prompt_length' => strlen($prompt),
            'url' => $tempUrl
        ]);

        // Configuração da requisição para a API com JSON mode
        $startTime = microtime(true);
        $response = Http::timeout(90)->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->visionModel,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Você é um assistente especializado em extrair informações de faturas de cartão de crédito e extratos bancários. Para cada transação, identifique: 1) Nome do estabelecimento (merchant_name), 2) Data da transação (transaction_date no formato YYYY-MM-DD), 3) Valor em centavos (amount como número inteiro, use valores negativos para débitos), 4) Descrição (description), 5) Categoria (category_code usando um dos códigos: FOOD, SUPER, TRANS, FUEL, STREAM, PHARM, ECOMM, DELIV, EDU, HEALTH, LEISURE, TRAVEL, CLOTH, SUBS, HOME, FUN, PIX, OTHER). Retorne os dados em formato JSON.'
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $prompt
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => $tempUrl
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens' => 4000
                ]);
        $duration = microtime(true) - $startTime;

        Log::info('Resposta recebida da API', [
            'status' => $response->status(),
            'duration' => round($duration, 2) . 's',
            'response_size' => strlen($response->body())
        ]);

        if ($response->failed()) {
            Log::error('Erro na API da OpenAI', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception('Erro ao processar o arquivo com a API da OpenAI: ' . ($response->json()['error']['message'] ?? 'Erro desconhecido'));
        }

        return $this->parseOpenAIResponse($response->json());
    }

    /**
     * Processa a fatura enviando o conteúdo diretamente para a API
     */
    private function processWithDirectUpload(string $fileContent, string $fileType): array
    {
        // Verificar se o arquivo é de um tipo de imagem suportado
        if (!in_array($fileType, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            throw new \Exception("Formato de arquivo não suportado para processamento de visão: $fileType");
        }

        // Codifica o arquivo em base64
        $base64File = base64_encode($fileContent);
        Log::debug('Arquivo codificado em base64', [
            'base64_length' => strlen($base64File)
        ]);

        // Prepara o prompt para a API
        $prompt = "Analise esta fatura de cartão de crédito ou extrato bancário e extraia todas as transações no formato JSON.";

        Log::debug('Enviando requisição para a API Vision com upload direto', [
            'model' => $this->visionModel,
            'prompt_length' => strlen($prompt)
        ]);

        // Configuração da requisição para a API Vision com JSON mode
        $startTime = microtime(true);
        $response = Http::timeout(90)->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->visionModel,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Você é um assistente especializado em extrair informações de faturas de cartão de crédito e extratos bancários. Para cada transação, identifique: 1) Nome do estabelecimento (merchant_name), 2) Data da transação (transaction_date no formato YYYY-MM-DD), 3) Valor em centavos (amount como número inteiro, use valores negativos para débitos), 4) Descrição (description), 5) Categoria (category_code usando um dos códigos: FOOD, SUPER, TRANS, FUEL, STREAM, PHARM, ECOMM, DELIV, EDU, HEALTH, LEISURE, TRAVEL, CLOTH, SUBS, HOME, FUN, PIX, OTHER). Retorne os dados em formato JSON.'
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $prompt
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:image/$fileType;base64,$base64File"
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens' => 4000
                ]);
        $duration = microtime(true) - $startTime;

        Log::info('Resposta recebida da API Vision', [
            'status' => $response->status(),
            'duration' => round($duration, 2) . 's',
            'response_size' => strlen($response->body())
        ]);

        if ($response->failed()) {
            Log::error('Erro na API da OpenAI', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception('Erro ao processar o arquivo com a API da OpenAI: ' . ($response->json()['error']['message'] ?? 'Erro desconhecido'));
        }

        return $this->parseOpenAIResponse($response->json());
    }

    /**
     * Processa com visão (dependendo do tipo de arquivo)
     */
    private function processWithVision(string $fileContent, string $fileType, string $filePath): array
    {
        try {
            Log::debug('Iniciando processamento com OpenAI Vision', [
                'model' => $this->visionModel,
                'file_type' => $fileType
            ]);

            // Para imagens, tente usar URL temporária do S3 primeiro
            try {
                return $this->processWithS3PresignedUrl($filePath, $fileType);
            } catch (\Exception $s3Error) {
                Log::warning('Falha ao processar imagem com S3 presigned URL, tentando envio direto', [
                    'error' => $s3Error->getMessage()
                ]);

                // Se falhar, tente envio direto
                return $this->processWithDirectUpload($fileContent, $fileType);
            }

        } catch (\Exception $e) {
            Log::error('Erro ao processar arquivo com OpenAI', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new HttpException(500, 'Erro ao processar o arquivo: ' . $e->getMessage());
        }
    }

    /**
     * Analisa a resposta da OpenAI e extrai as transações
     */
    private function parseOpenAIResponse(array $responseData): array
    {
        // Extrai o texto da resposta
        $content = $responseData['choices'][0]['message']['content'] ?? '';
        Log::debug('Conteúdo da resposta extraído', [
            'content_length' => strlen($content)
        ]);

        // Como estamos usando JSON mode, o conteúdo já deve ser um JSON válido
        try {
            $transactions = json_decode($content, true);

            // Verificar diferentes possíveis estruturas de resposta
            if (isset($transactions['transactions']) && is_array($transactions['transactions'])) {
                $transactions = $transactions['transactions'];
            } elseif (isset($transactions['data']) && is_array($transactions['data'])) {
                $transactions = $transactions['data'];
            } elseif (isset($transactions['results']) && is_array($transactions['results'])) {
                $transactions = $transactions['results'];
            } elseif (isset($transactions['items']) && is_array($transactions['items'])) {
                $transactions = $transactions['items'];
            }

            // Se for objeto ou não for um array, converter para array
            if (!is_array($transactions) || (is_array($transactions) && !isset($transactions[0]) && count($transactions) > 0)) {
                $transactions = [$transactions];
            }

            // Remover valores vazios, nulos ou inválidos
            $validTransactions = [];
            foreach ($transactions as $transaction) {
                if (empty($transaction) || !is_array($transaction)) {
                    continue;
                }

                // Verificar campos mínimos obrigatórios
                if (!isset($transaction['merchant_name']) || !isset($transaction['amount'])) {
                    continue;
                }

                // Normalizar dados
                if (!isset($transaction['transaction_date']) || empty($transaction['transaction_date'])) {
                    // Usar a data atual como fallback se não houver data
                    $transaction['transaction_date'] = date('Y-m-d');
                }

                // Garantir que amount seja um inteiro
                if (isset($transaction['amount']) && !is_int($transaction['amount'])) {
                    // Se for string com vírgula, converter
                    if (is_string($transaction['amount']) && strpos($transaction['amount'], ',') !== false) {
                        $transaction['amount'] = (int) (str_replace(['.', ','], ['', '.'], $transaction['amount']) * 100);
                    }
                    // Se for float, multiplicar por 100
                    elseif (is_float($transaction['amount'])) {
                        $transaction['amount'] = (int) ($transaction['amount'] * 100);
                    }
                    // Se ainda for string, tentar converter diretamente
                    elseif (is_string($transaction['amount'])) {
                        $transaction['amount'] = (int) $transaction['amount'];
                    }
                }

                // Adicionar à lista de transações válidas
                $validTransactions[] = $transaction;
            }

            Log::info('Transações válidas extraídas', [
                'count' => count($validTransactions)
            ]);

            foreach ($validTransactions as $index => $transaction) {
                Log::debug('Transação extraída', [
                    'index' => $index,
                    'merchant' => $transaction['merchant_name'] ?? 'N/A',
                    'amount' => $transaction['amount'] ?? 0,
                    'date' => $transaction['transaction_date'] ?? 'N/A',
                    'category' => $transaction['category_code'] ?? 'N/A'
                ]);
            }

            return $validTransactions;

        } catch (\Exception $e) {
            Log::error('Erro ao decodificar JSON da resposta', [
                'error' => $e->getMessage(),
                'content' => substr($content, 0, 500) . '...'
            ]);

            // Fallback: tentar extrair um array JSON da resposta
            if (preg_match('/\[\s*{.*}\s*\]/s', $content, $matches)) {
                $extractedJson = $matches[0];
                try {
                    $transactions = json_decode($extractedJson, true);

                    if (is_array($transactions)) {
                        Log::debug('Array JSON extraído do texto', [
                            'extracted_json' => $extractedJson
                        ]);
                        return $transactions;
                    }
                } catch (\Exception $jsonError) {
                    Log::error('Erro ao decodificar JSON extraído', [
                        'error' => $jsonError->getMessage()
                    ]);
                }
            }

            return []; // Retorna array vazio em caso de falha na extração
        }
    }

    /**
     * Processa arquivo CSV
     */
    private function processCsvContent(string $content): array
    {
        try {
            Log::debug('Iniciando processamento de CSV', [
                'content_length' => strlen($content)
            ]);

            $rows = array_map('str_getcsv', explode("\n", $content));
            Log::debug('Linhas CSV extraídas', ['row_count' => count($rows)]);

            // Remove a linha de cabeçalho
            $header = array_shift($rows);
            Log::debug('Cabeçalho do CSV', ['header' => $header]);

            $transactions = [];

            foreach ($rows as $index => $row) {
                if (count($row) >= 3) { // Validação básica
                    $merchantName = $row[0];

                    // Tenta categorizar a transação
                    Log::debug('Categorizando transação', [
                        'merchant' => $merchantName
                    ]);
                    $categoryCode = $this->categorizeTransactionHeuristic($merchantName);

                    $amount = (int) ($row[2] * 100);
                    $date = date('Y-m-d', strtotime($row[1]));

                    $transactions[] = [
                        'merchant_name' => $merchantName,
                        'transaction_date' => $date,
                        'amount' => $amount,
                        'description' => $row[3] ?? null,
                        'category_code' => $categoryCode,
                    ];

                    Log::debug('Transação processada do CSV', [
                        'index' => $index,
                        'merchant' => $merchantName,
                        'date' => $date,
                        'amount' => $amount,
                        'category' => $categoryCode
                    ]);
                } else {
                    Log::warning('Linha CSV ignorada por falta de dados', [
                        'index' => $index,
                        'row' => $row
                    ]);
                }
            }

            Log::info('Processamento de CSV concluído', [
                'transaction_count' => count($transactions)
            ]);

            return $transactions;
        } catch (\Exception $e) {
            Log::error('Erro ao processar CSV', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Categoriza transação (usando IA)
     */
    public function categorizeTransaction(string $merchantName): ?string
    {
        try {
            Log::debug('Iniciando categorização de transação', [
                'merchant' => $merchantName
            ]);

            // Primeiro tentar categorização heurística
            $category = $this->categorizeTransactionHeuristic($merchantName);
            if ($category) {
                Log::debug('Categoria encontrada por heurística', [
                    'merchant' => $merchantName,
                    'category' => $category
                ]);
                return $category;
            }

            // Se não conseguir, usar a API
            $startTime = microtime(true);
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $this->categorizationModel,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'Você é um classificador de transações financeiras. Categorize o estabelecimento em uma das seguintes categorias: FOOD (alimentação), SUPER (supermercados), TRANS (transporte), FUEL (combustível), STREAM (streaming), PHARM (farmácias), ECOMM (e-commerce), DELIV (delivery), EDU (educação), HEALTH (saúde), LEISURE (lazer), TRAVEL (viagem), CLOTH (vestuário), SUBS (assinaturas), HOME (casa), FUN (diversão), PIX (transferências), OTHER (outros). Responda apenas com o código da categoria, nada mais.'
                            ],
                            [
                                'role' => 'user',
                                'content' => "Categorize este estabelecimento: $merchantName"
                            ]
                        ],
                        'max_tokens' => 10,
                        'temperature' => 0.1
                    ]);
            $duration = microtime(true) - $startTime;

            Log::debug('Resposta de categorização recebida', [
                'status' => $response->status(),
                'duration' => round($duration, 3) . 's'
            ]);

            if ($response->failed()) {
                Log::warning('Falha na API da OpenAI durante categorização', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return 'OTHER'; // Categoria padrão em caso de falha
            }

            $responseData = $response->json();
            $categoryCode = trim($responseData['choices'][0]['message']['content'] ?? '');

            // Valida se é um código de categoria válido
            $validCodes = ['FOOD', 'SUPER', 'TRANS', 'FUEL', 'STREAM', 'PHARM', 'ECOMM', 'DELIV', 'EDU', 'HEALTH', 'LEISURE', 'TRAVEL', 'CLOTH', 'SUBS', 'HOME', 'FUN', 'PIX', 'OTHER'];

            $isValid = in_array($categoryCode, $validCodes);

            Log::debug('Categorização concluída', [
                'merchant' => $merchantName,
                'category' => $categoryCode,
                'is_valid' => $isValid
            ]);

            return $isValid ? $categoryCode : 'OTHER';

        } catch (\Exception $e) {
            Log::warning('Erro ao categorizar transação', [
                'merchant' => $merchantName,
                'error' => $e->getMessage()
            ]);

            return 'OTHER'; // Categoria padrão em caso de erro
        }
    }

    /**
     * Sugere o melhor cartão para um estabelecimento
     */
    public function suggestBestCard(string $merchantName, array $userCards): array
    {
        try {
            Log::info('Iniciando sugestão de melhor cartão', [
                'merchant' => $merchantName,
                'card_count' => count($userCards)
            ]);

            $cardsJson = json_encode($userCards);

            $startTime = microtime(true);
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $this->categorizationModel,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'Você é um especialista em cartões de crédito e programas de pontos. Analise a lista de cartões do usuário e sugira o melhor para o estabelecimento mencionado. Responda em formato JSON com os campos: best_card_id, reason, estimated_points_per_real'
                            ],
                            [
                                'role' => 'user',
                                'content' => "Estabelecimento: $merchantName\n\nCartões disponíveis: $cardsJson\n\nRetorne sua análise em formato JSON."
                            ]
                        ],
                        'response_format' => ['type' => 'json_object'],
                        'max_tokens' => 200,
                        'temperature' => 0.1
                    ]);
            $duration = microtime(true) - $startTime;

            Log::debug('Resposta de sugestão de cartão recebida', [
                'status' => $response->status(),
                'duration' => round($duration, 2) . 's'
            ]);

            if ($response->failed()) {
                Log::warning('Falha na API da OpenAI durante sugestão de cartão', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [
                    'success' => false,
                    'message' => 'Não foi possível analisar os cartões no momento'
                ];
            }

            $responseData = $response->json();
            $content = $responseData['choices'][0]['message']['content'] ?? '';

            // Como estamos usando JSON mode, o conteúdo já deve ser um JSON válido
            $suggestion = json_decode($content, true);

            if (!is_array($suggestion)) {
                Log::error('Formato de resposta inválido para sugestão de cartão', [
                    'content' => $content
                ]);
                return [
                    'success' => false,
                    'message' => 'Formato de resposta inválido'
                ];
            }

            // Encontrar o cartão recomendado
            $bestCard = null;
            foreach ($userCards as $card) {
                if ($card['id'] === $suggestion['best_card_id']) {
                    $bestCard = $card;
                    break;
                }
            }

            Log::info('Sugestão de cartão concluída', [
                'best_card_id' => $suggestion['best_card_id'] ?? 'N/A',
                'card_found' => $bestCard !== null
            ]);

            return [
                'success' => true,
                'card' => $bestCard,
                'reason' => $suggestion['reason'] ?? 'Este cartão oferece melhores benefícios para este estabelecimento',
                'estimated_points' => $suggestion['estimated_points_per_real'] ?? 'Varia conforme o gasto'
            ];

        } catch (\Exception $e) {
            Log::error('Erro ao sugerir cartão', [
                'merchant' => $merchantName,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao processar a sugestão de cartão'
            ];
        }
    }
}