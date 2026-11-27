<?php

namespace App\Modules\Financial\Domain;

enum FinancialStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
