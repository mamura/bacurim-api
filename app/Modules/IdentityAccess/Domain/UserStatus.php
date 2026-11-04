<?php

namespace App\Modules\IdentityAccess\Domain;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
