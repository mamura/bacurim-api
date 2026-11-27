<?php

namespace App\Modules\Financial\Domain;

enum EconomicNature: string
{
    case Income = 'income';
    case Cost = 'cost';
    case Expense = 'expense';
}
