<?php

namespace Domains\Shared\Actions\Address;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GetAddressByCEP
{
    public static function execute($cep)
    {
        $cep = str_replace('-', '', $cep);
        if (!self::isValidCep($cep)) {
            throw new HttpException(400, 'Invalid CEP');
        }
        
        $url = "https://viacep.com.br/ws/{$cep}/json/";
        
        try {
            $client = new Client();
            $response = $client->get($url);
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new HttpException(500, 'Error fetching address: ' . $e->getMessage());
        }
    }

    private static function isValidCep($cep)
    {
        return preg_match('/^[0-9]{8}$/', $cep);
    }
}