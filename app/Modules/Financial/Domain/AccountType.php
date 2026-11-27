<?php

namespace App\Modules\Financial\Domain;

enum AccountType: string
{
    case Bank = 'bank';
    case Digital = 'digital';
    case Cash = 'cash';
    case Other = 'other';
}
