<?php

namespace App\Modules\Financial\Domain\Entities;

use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Domain\Exceptions\InvalidFinancialData;
use App\Modules\Financial\Domain\FinancialStatus;
use App\Modules\Financial\Domain\ValueObjects\FinancialIdentity;
use App\Modules\Financial\Domain\ValueObjects\FinancialText;

final readonly class FinancialCategory
{
    public string $name;

    public ?string $parentPublicId;

    public function __construct(public FinancialIdentity $identity, string $name, public EconomicNature $nature, public FinancialStatus $status = FinancialStatus::Active, ?string $parentPublicId = null, public ?string $defaultKey = null)
    {
        $this->name = FinancialText::name($name);
        $this->parentPublicId = $parentPublicId === null ? null : FinancialText::uuid($parentPublicId);
        if ($this->parentPublicId === $identity->publicId) {
            throw new InvalidFinancialData('A category cannot be its own parent.');
        }
    }
}
