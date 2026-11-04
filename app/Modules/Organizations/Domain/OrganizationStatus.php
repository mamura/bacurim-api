<?php

namespace App\Modules\Organizations\Domain;

enum OrganizationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
