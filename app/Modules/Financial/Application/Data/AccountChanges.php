<?php

namespace App\Modules\Financial\Application\Data;

use App\Modules\Financial\Domain\AccountType;
use App\Modules\Financial\Domain\FinancialStatus;

final readonly class AccountChanges
{
    public function __construct(public ?string $name = null, public ?AccountType $type = null, public ?FinancialStatus $status = null) {}
}
