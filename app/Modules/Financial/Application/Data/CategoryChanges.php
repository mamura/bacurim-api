<?php

namespace App\Modules\Financial\Application\Data;

use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Domain\FinancialStatus;

final readonly class CategoryChanges
{
    public function __construct(public ?string $name = null, public ?EconomicNature $nature = null, public ?FinancialStatus $status = null, public bool $changeParent = false, public ?string $parentPublicId = null) {}
}
