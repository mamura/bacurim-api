<?php

namespace App\Modules\Financial\Domain;

enum CommitmentNature: string
{
    case Receivable = 'receivable';
    case Payable = 'payable';
}
