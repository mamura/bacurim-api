<?php

namespace App\Modules\Financial\Domain\Entities;

use App\Modules\Financial\Domain\CommitmentNature;
use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Domain\Exceptions\InvalidFinancialData;
use App\Modules\Financial\Domain\ValueObjects\FinancialDate;
use App\Modules\Financial\Domain\ValueObjects\FinancialIdentity;
use App\Modules\Financial\Domain\ValueObjects\Money;

final readonly class FinancialCommitment
{
    public CommitmentNature $nature;

    public function __construct(public FinancialIdentity $identity, public EconomicEvent $event, public Money $expectedAmount, public FinancialDate $dueDate)
    {
        if ($event->identity->organizationId !== $identity->organizationId) {
            throw new InvalidFinancialData('A commitment must belong to its event organization.');
        }
        $expectedAmount->requirePositive();
        $this->nature = $event->nature === EconomicNature::Income ? CommitmentNature::Receivable : CommitmentNature::Payable;
    }

    public function pendingAmount(): Money
    {
        return $this->expectedAmount;
    }

    public function temporalStatus(FinancialDate $today): string
    {
        return $this->dueDate->value < $today->value ? 'overdue' : 'upcoming';
    }
}
