<?php

namespace App\Modules\Financial\Infrastructure\Services;

use App\Modules\Financial\Application\Contracts\FinancialClock;
use DateTimeImmutable;
use DateTimeZone;

final class SystemFinancialClock implements FinancialClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
