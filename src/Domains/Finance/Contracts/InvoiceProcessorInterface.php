<?php

namespace Domains\Finance\Contracts;

interface InvoiceProcessorInterface
{
    /**
     * Processa um arquivo de fatura e extrai as transações
     * 
     * @param string $filePath Caminho completo do arquivo no storage
     * @param string $fileType Tipo do arquivo (pdf, jpg, png, csv)
     * @return array Array de transações extraídas da fatura
     */
    public function processInvoice(string $filePath, string $fileType): array;
    
    /**
     * Categoriza uma transação com base no nome do estabelecimento
     * 
     * @param string $merchantName Nome do estabelecimento
     * @return string|null Código da categoria sugerida ou null se não houver sugestão
     */
    public function categorizeTransaction(string $merchantName): ?string;
    
    /**
     * Sugere o melhor cartão para um determinado estabelecimento
     * 
     * @param string $merchantName Nome do estabelecimento
     * @param array $userCards Array com os cartões disponíveis do usuário
     * @return array Sugestão de cartão e programa de recompensas
     */
    public function suggestBestCard(string $merchantName, array $userCards): array;
}