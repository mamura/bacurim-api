<?php

namespace App\Modules\Financial\Application\Data;

final readonly class EconomicEventChanges
{
    public function __construct(public ?string $description = null, public ?string $amount = null, public ?string $competenceDate = null, public ?string $categoryPublicId = null, public ?string $dueDate = null, public ?string $expectedAmount = null) {}
}
