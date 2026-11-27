<?php

namespace App\Modules\Financial\Domain\ValueObjects;

use App\Modules\Financial\Domain\Exceptions\InvalidFinancialData;
use DateTimeImmutable;

final readonly class FinancialDate
{
    public function __construct(public string $value)
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $value) || ! $parsed || $parsed->format('Y-m-d') !== $value) {
            throw new InvalidFinancialData('A valid civil date in YYYY-MM-DD is required.');
        }
    }
}
