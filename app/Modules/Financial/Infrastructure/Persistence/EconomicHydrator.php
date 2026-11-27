<?php

namespace App\Modules\Financial\Infrastructure\Persistence;

use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Domain\Entities\EconomicEvent;
use App\Modules\Financial\Domain\Entities\FinancialCategory;
use App\Modules\Financial\Domain\Entities\FinancialCommitment;
use App\Modules\Financial\Domain\ValueObjects\FinancialDate;
use App\Modules\Financial\Domain\ValueObjects\Money;

final readonly class EconomicHydrator
{
    public function __construct(private FinancialStorage $storage) {}

    /** @param array<string,mixed> $row */
    public function event(array $row, FinancialCategory $category): EconomicEvent
    {
        return new EconomicEvent($this->storage->identity($row), (string) $row['description'], EconomicNature::from((string) $row['nature']), new Money((string) $row['amount'], (string) $row['currency']), new FinancialDate((string) $row['competence_date']), $category, (string) $row['created_by']);
    }

    /** @param array<string,mixed> $row */
    public function commitment(array $row, EconomicEvent $event): FinancialCommitment
    {
        $commitment = new FinancialCommitment($this->storage->identity($row), $event, new Money((string) $row['expected_amount'], (string) $row['currency']), new FinancialDate((string) $row['due_date']));
        if ($commitment->nature->value !== (string) $row['nature'] || $row['status'] !== 'pending') {
            throw new \LogicException('Unsupported financial lifecycle state.');
        }

        return $commitment;
    }
}
