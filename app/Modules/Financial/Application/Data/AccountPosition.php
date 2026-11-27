<?php

namespace App\Modules\Financial\Application\Data;

use App\Modules\Financial\Domain\ValueObjects\Money;
use DateTimeImmutable;

/** A null balance means no authoritative ledger position is available, never zero. */
final readonly class AccountPosition
{
    public function __construct(public string $accountPublicId, public string $currency, public ?Money $balance, public DateTimeImmutable $asOf, public string $source) {}
}
