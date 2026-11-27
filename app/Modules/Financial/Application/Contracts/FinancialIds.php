<?php

namespace App\Modules\Financial\Application\Contracts;

interface FinancialIds
{
    public function generate(): string;
}
