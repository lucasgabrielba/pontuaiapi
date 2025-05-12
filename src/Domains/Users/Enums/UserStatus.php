<?php

namespace Domains\Users\Enums;

enum UserStatus: string
{
    case ACTIVE = 'Ativo';
    case INACTIVE = 'Inativo';
}
