<?php

namespace Domains\Shared\Services;

use Domains\Shared\Actions\Address\GetAddressByCEP;

class AddressesService
{
    public function getAddressByCEP(string $cep)
    {
        return GetAddressByCEP::execute($cep);
    }
}
