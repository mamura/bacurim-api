<?php

namespace App\Modules\Financial\Domain\Entities;

use App\Modules\Financial\Domain\Exceptions\InvalidFinancialData;
use App\Modules\Financial\Domain\ValueObjects\Money;

final readonly class EconomicRecord
{
    /** @param list<FinancialCommitment> $commitments */
    public function __construct(public EconomicEvent $event, public array $commitments, public ?\DateTimeImmutable $lockedAt = null)
    {
        if ($commitments === []) {
            throw new InvalidFinancialData('An economic event requires commitments.');
        }
        $total = new Money('0');
        $ids = [];
        foreach ($commitments as $commitment) {
            if ($commitment->event != $event || in_array($commitment->identity->publicId, $ids, true)) {
                throw new InvalidFinancialData('Commitments must reference the same event with distinct identities.');
            }
            $ids[] = $commitment->identity->publicId;
            $total = $total->plus($commitment->expectedAmount);
        }
        if ($total->minor !== $event->amount->minor) {
            throw new InvalidFinancialData('Commitment total must equal the economic value.');
        }
    }
}
