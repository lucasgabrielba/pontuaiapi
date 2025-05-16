<?php

namespace Domains\Finance\Services;

use Domains\Finance\Contracts\InvoiceProcessorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Spatie\PdfToImage\Pdf;

class OpenAIProcessorService implements InvoiceProcessorInterface
{
    protected string $apiKey;
    protected string $visionModel;
    protected string $documentModel;
    
    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
        $this->visionModel = config('services.openai.vision_model', 'gpt-4o');
        $this->documentModel = config('services.openai.document_model', 'gpt-4-turbo');
        
        if (!$this->apiKey) {
            Log::error('API Key da OpenAI não configurada');
            throw new \Exception('API Key da OpenAI não configurada');
        }
        
        Log::debug('OpenAIProcessorService inicializado', [
            'vision_model' => $this->visionModel,
            'document_model' => $this->documentModel
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
        
        // Para PDFs e imagens usamos a API Vision
        if (in_array($fileType, ['pdf', 'jpg', 'jpeg', 'png'])) {
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
    
    private function processWithVision(string $fileContent, string $fileType, string $filePath): array
    {
        try {
            Log::debug('Iniciando processamento com OpenAI Vision', [
                'model' => $this->visionModel,
                'file_type' => $fileType
            ]);
            
            // Para PDFs, primeiro tente usar a API que suporta PDF
            if ($fileType === 'pdf') {
                try {
                    Log::info('Tentando processar PDF diretamente com API de documentos');
                    return $this->processWithDocumentAPI($fileContent, $filePath);
                } catch (\Exception $docError) {
                    Log::warning('Falha ao processar com API de documentos, tentando S3 presigned URL', [
                        'error' => $docError->getMessage()
                    ]);
                    
                    // Se falhar, tente usar URL temporária do S3
                    try {
                        return $this->processWithS3PresignedUrl($filePath, $fileType);
                    } catch (\Exception $s3Error) {
                        Log::warning('Falha ao processar com S3 presigned URL, tentando conversão para imagem', [
                            'error' => $s3Error->getMessage()
                        ]);
                        
                        // Se ainda falhar, use a conversão como último recurso
                        return $this->processWithPdfConversion($fileContent, $filePath);
                    }
                }
            }
            
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
     * Processa o arquivo usando URL temporária do S3
     */
    private function processWithS3PresignedUrl(string $filePath, string $fileType): array
    {
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
        
        // Prepara o prompt para a API
        $prompt = "Analise esta fatura de cartão de crédito e extraia todas as transações no formato JSON. 
                  Para cada transação, identifique: 
                  1. Nome do estabelecimento (merchant_name)
                  2. Data da transação (transaction_date no formato YYYY-MM-DD)
                  3. Valor em centavos (amount como número inteiro)
                  4. Descrição, se houver (description)
                  5. Categoria (category_code usando um dos códigos: SUPER para supermercados, STREAM para streaming, 
                     ECOMM para e-commerce, FUEL para combustível, RESTA para restaurantes, PHARM para farmácias, 
                     TRANS para transporte, DELIV para delivery)";
        
        Log::debug('Enviando requisição para a API com URL temporária do S3', [
            'model' => $fileType === 'pdf' ? $this->documentModel : $this->visionModel,
            'prompt_length' => strlen($prompt),
            'url' => $tempUrl
        ]);
        
        // Escolha o modelo com base no tipo de arquivo
        $model = $fileType === 'pdf' ? $this->documentModel : $this->visionModel;
        
        // Configuração da requisição para a API
        $startTime = microtime(true);
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [
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
     * Processa PDF diretamente usando a API de documentos da OpenAI
     */
    private function processWithDocumentAPI(string $fileContent, string $filePath): array
    {
        // Codifica o arquivo em base64
        $base64File = base64_encode($fileContent);
        Log::debug('PDF codificado em base64 para API de documentos', [
            'base64_length' => strlen($base64File)
        ]);
        
        // Prepara o prompt para a API
        $prompt = "Analise esta fatura de cartão de crédito e extraia todas as transações no formato JSON. 
                  Para cada transação, identifique: 
                  1. Nome do estabelecimento (merchant_name)
                  2. Data da transação (transaction_date no formato YYYY-MM-DD)
                  3. Valor em centavos (amount como número inteiro)
                  4. Descrição, se houver (description)
                  5. Categoria (category_code usando um dos códigos: SUPER para supermercados, STREAM para streaming, 
                     ECOMM para e-commerce, FUEL para combustível, RESTA para restaurantes, PHARM para farmácias, 
                     TRANS para transporte, DELIV para delivery)";
        
        Log::debug('Enviando requisição para a API de Documentos', [
            'model' => $this->documentModel,
            'prompt_length' => strlen($prompt)
        ]);
        
        // Configuração da requisição para a API de documentos
        $startTime = microtime(true);
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->documentModel,
            'messages' => [
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
                                'url' => "data:application/pdf;base64,$base64File"
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => 4000
        ]);
        $duration = microtime(true) - $startTime;
        
        Log::info('Resposta recebida da API de Documentos', [
            'status' => $response->status(),
            'duration' => round($duration, 2) . 's',
            'response_size' => strlen($response->body())
        ]);
        
        if ($response->failed()) {
            Log::error('Erro na API de Documentos da OpenAI', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception('Erro ao processar o arquivo PDF com a API da OpenAI: ' . ($response->json()['error']['message'] ?? 'Erro desconhecido'));
        }
        
        return $this->parseOpenAIResponse($response->json());
    }
    
    /**
     * Fallback: Processa PDF convertendo-o para imagens primeiro
     */
    private function processWithPdfConversion(string $fileContent, string $filePath): array
    {
        // Salvar o PDF temporariamente
        $tempPdfPath = sys_get_temp_dir() . '/' . basename($filePath);
        file_put_contents($tempPdfPath, $fileContent);
        
        Log::debug('PDF salvo temporariamente para conversão', [
            'temp_path' => $tempPdfPath
        ]);
        
        // Converter o PDF em imagens (primeira página apenas, para simplificar)
        $converter = new Pdf($tempPdfPath);
        $tempImagePath = sys_get_temp_dir() . '/page_1.jpg';
        $converter->setOutputFormat('jpg')
                ->setPage(1)
                ->saveImage($tempImagePath);
        
        Log::debug('PDF convertido para imagem', [
            'image_path' => $tempImagePath
        ]);
        
        // Processar a imagem com a API Vision
        $imageContent = file_get_contents($tempImagePath);
        $result = $this->processWithDirectUpload($imageContent, 'jpg');
        
        // Limpar arquivos temporários
        unlink($tempPdfPath);
        unlink($tempImagePath);
        
        return $result;
    }
    
    /**
     * Processa a fatura enviando o conteúdo diretamente para a API
     */
    private function processWithDirectUpload(string $fileContent, string $fileType): array
    {
        // Codifica o arquivo em base64
        $base64File = base64_encode($fileContent);
        Log::debug('Arquivo codificado em base64', [
            'base64_length' => strlen($base64File)
        ]);
        
        // Prepara o prompt para a API
        $prompt = "Analise esta fatura de cartão de crédito e extraia todas as transações no formato JSON. 
                  Para cada transação, identifique: 
                  1. Nome do estabelecimento (merchant_name)
                  2. Data da transação (transaction_date no formato YYYY-MM-DD)
                  3. Valor em centavos (amount como número inteiro)
                  4. Descrição, se houver (description)
                  5. Categoria (category_code usando um dos códigos: SUPER para supermercados, STREAM para streaming, 
                     ECOMM para e-commerce, FUEL para combustível, RESTA para restaurantes, PHARM para farmácias, 
                     TRANS para transporte, DELIV para delivery)";
        
        Log::debug('Enviando requisição para a API Vision com upload direto', [
            'model' => $this->visionModel,
            'prompt_length' => strlen($prompt)
        ]);
        
        // Configuração da requisição para a API Vision
        $startTime = microtime(true);
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->visionModel,
            'messages' => [
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
     * Analisa a resposta da OpenAI e extrai as transações
     */
    private function parseOpenAIResponse(array $responseData): array
    {
        // Extrai o texto da resposta
        $content = $responseData['choices'][0]['message']['content'] ?? '';
        Log::debug('Conteúdo da resposta extraído', [
            'content_length' => strlen($content)
        ]);
        
        // Extrair apenas o JSON da resposta (pode estar entre ```json e ```)
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $jsonContent = $matches[1];
            Log::debug('JSON extraído do bloco de código', [
                'json_length' => strlen($jsonContent)
            ]);
        } else {
            $jsonContent = $content;
            Log::debug('Usando conteúdo completo como JSON');
        }
        
        // Decodifica o JSON de transações
        $transactions = json_decode($jsonContent, true);
        
        // Se não for um array válido, tenta encontrar um array dentro do texto
        if (!is_array($transactions)) {
            Log::warning('Falha na decodificação do JSON, tentando extrair array', [
                'json_content' => substr($jsonContent, 0, 200) . '...'
            ]);
            
            if (preg_match('/\[\s*{.*}\s*\]/s', $jsonContent, $matches)) {
                $transactions = json_decode($matches[0], true);
                Log::debug('Array JSON extraído do texto', [
                    'extracted_json' => $matches[0]
                ]);
            }
        }
        
        // Último recurso caso ainda não seja um array válido
        if (!is_array($transactions)) {
            Log::error('Falha ao extrair JSON da resposta da OpenAI', [
                'content' => substr($content, 0, 500) . '...'
            ]);
            throw new \Exception('Formato de resposta inválido da OpenAI');
        }
        
        Log::info('Transações extraídas com sucesso', [
            'count' => count($transactions)
        ]);
        
        foreach ($transactions as $index => $transaction) {
            Log::debug('Transação extraída', [
                'index' => $index,
                'merchant' => $transaction['merchant_name'] ?? 'N/A',
                'amount' => $transaction['amount'] ?? 0,
                'date' => $transaction['transaction_date'] ?? 'N/A',
                'category' => $transaction['category_code'] ?? 'N/A'
            ]);
        }
        
        return $transactions;
    }
    
    
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
                    $categoryCode = $this->categorizeTransaction($merchantName);
                    
                    $amount = (int)($row[2] * 100);
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
    
    public function categorizeTransaction(string $merchantName): ?string
    {
        try {
            Log::debug('Iniciando categorização de transação', [
                'merchant' => $merchantName
            ]);
            
            $startTime = microtime(true);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo', // Mantendo este modelo para categorização simples
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é um classificador de transações financeiras. Categorize o estabelecimento em uma das seguintes categorias: SUPER (supermercados), STREAM (streaming), ECOMM (e-commerce), FUEL (combustível), RESTA (restaurantes), PHARM (farmácias), TRANS (transporte), DELIV (delivery). Responda apenas com o código da categoria, nada mais.'
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
                return null;
            }
            
            $responseData = $response->json();
            $categoryCode = trim($responseData['choices'][0]['message']['content'] ?? '');
            
            // Valida se é um código de categoria válido
            $validCodes = ['SUPER', 'STREAM', 'ECOMM', 'FUEL', 'RESTA', 'PHARM', 'TRANS', 'DELIV'];
            
            $isValid = in_array($categoryCode, $validCodes);
            
            Log::debug('Categorização concluída', [
                'merchant' => $merchantName,
                'category' => $categoryCode,
                'is_valid' => $isValid
            ]);
            
            return $isValid ? $categoryCode : null;
            
        } catch (\Exception $e) {
            Log::warning('Erro ao categorizar transação', [
                'merchant' => $merchantName,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    public function suggestBestCard(string $merchantName, array $userCards): array
    {
        try {
            Log::info('Iniciando sugestão de melhor cartão', [
                'merchant' => $merchantName,
                'card_count' => count($userCards)
            ]);
            
            $cardsJson = json_encode($userCards);
            
            $startTime = microtime(true);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é um especialista em cartões de crédito e programas de pontos. Analise a lista de cartões do usuário e sugira o melhor para o estabelecimento mencionado.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Estabelecimento: $merchantName\n\nCartões disponíveis: $cardsJson\n\nQual o melhor cartão para este estabelecimento e por quê? Responda em formato JSON com os campos: best_card_id, reason, estimated_points_per_real"
                    ]
                ],
                'max_tokens' => 200
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
            
            // Extrair o JSON da resposta
            if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
                $jsonContent = $matches[1];
                Log::debug('JSON extraído do bloco de código', [
                    'json_content' => $jsonContent
                ]);
            } else {
                $jsonContent = $content;
                Log::debug('Usando conteúdo completo como JSON');
            }
            
            $suggestion = json_decode($jsonContent, true);
            
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