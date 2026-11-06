<?php

namespace App\Modules\IdentityAccess\Domain;

enum UserProfile: string
{
    case Standard = 'standard';
    case Consultant = 'consultant';
}
