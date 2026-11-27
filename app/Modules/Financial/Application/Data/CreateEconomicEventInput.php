<?php

namespace App\Modules\Financial\Application\Data;

use App\Modules\Financial\Domain\EconomicNature;

final readonly class CreateEconomicEventInput
{
    public function __construct(public string $description, public EconomicNature $nature, public string $amount, public string $competenceDate, public string $categoryPublicId, public string $dueDate, public ?string $expectedAmount = null, public string $currency = 'BRL') {}
}
