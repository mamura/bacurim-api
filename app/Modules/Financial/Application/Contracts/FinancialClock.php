<?php

namespace App\Modules\Financial\Application\Contracts;

use DateTimeImmutable;

interface FinancialClock
{
    public function now(): DateTimeImmutable;
}
