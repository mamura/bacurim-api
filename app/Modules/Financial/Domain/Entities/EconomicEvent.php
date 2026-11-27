<?php

namespace App\Modules\Financial\Domain\Entities;

use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Domain\Exceptions\InvalidFinancialData;
use App\Modules\Financial\Domain\FinancialStatus;
use App\Modules\Financial\Domain\ValueObjects\FinancialDate;
use App\Modules\Financial\Domain\ValueObjects\FinancialIdentity;
use App\Modules\Financial\Domain\ValueObjects\FinancialText;
use App\Modules\Financial\Domain\ValueObjects\Money;

final readonly class EconomicEvent
{
    public string $description;

    public string $createdBy;

    public function __construct(public FinancialIdentity $identity, string $description, public EconomicNature $nature, public Money $amount, public FinancialDate $competenceDate, public FinancialCategory $category, string $createdBy)
    {
        $this->description = FinancialText::name($description);
        $this->createdBy = FinancialText::uuid($createdBy);
        $amount->requirePositive();
        if ($category->identity->organizationId !== $identity->organizationId || $category->nature !== $nature) {
            throw new InvalidFinancialData('Category must belong to this organization and match the economic nature.');
        }
        // Active eligibility is checked at creation/reclassification, not when restoring historical events.
    }

    public function requireEligibleCategory(): void
    {
        if ($this->category->status !== FinancialStatus::Active) {
            throw new InvalidFinancialData('An active category is required.');
        }
    }
}
