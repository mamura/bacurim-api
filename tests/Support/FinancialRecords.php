<?php

namespace Tests\Support;

use App\Modules\Financial\Application\Contracts\FinancialClock;
use App\Modules\Financial\Application\Contracts\Persistence\EconomicRecordRepository;
use App\Modules\Financial\Application\FinancialOperation;
use App\Modules\Financial\Application\UseCases\ManageFinancialCategories;
use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Domain\Entities\EconomicEvent;
use App\Modules\Financial\Domain\Entities\EconomicRecord;
use App\Modules\Financial\Domain\Entities\FinancialCommitment;
use App\Modules\Financial\Domain\ValueObjects\FinancialDate;
use App\Modules\Financial\Domain\ValueObjects\FinancialIdentity;
use App\Modules\Financial\Domain\ValueObjects\Money;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Support\Str;

trait FinancialRecords
{
    /** @param list<array{amount:string,due_date:string}> $parts */
    protected function economicRecord(TenantContext $context, EconomicNature $nature = EconomicNature::Income, string $amount = '100.00', array $parts = []): EconomicRecord
    {
        $category = app(ManageFinancialCategories::class)->create($context, 'Classification', $nature);
        $now = app(FinancialClock::class)->now();
        $id = fn () => new FinancialIdentity(null, (string) Str::uuid(), $context->organizationId, 0, $now, $now);
        $event = new EconomicEvent($id(), 'Service', $nature, new Money($amount), new FinancialDate('2026-01-15'), $category, $context->actorPublicId);
        if ($parts === []) {
            $parts = [['amount' => $amount, 'due_date' => '2026-02-15']];
        }
        $children = array_map(fn ($part) => new FinancialCommitment($id(), $event, new Money($part['amount']), new FinancialDate($part['due_date'])), $parts);

        return app(FinancialOperation::class)->run($context, true, fn () => app(EconomicRecordRepository::class)->save($context, new EconomicRecord($event, $children)));
    }
}
