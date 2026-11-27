<?php

namespace App\Modules\Financial\Application\UseCases;

use App\Modules\Financial\Application\Contracts\FinancialClock;
use App\Modules\Financial\Application\Contracts\FinancialIds;
use App\Modules\Financial\Application\Contracts\Persistence\EconomicRecordRepository;
use App\Modules\Financial\Application\Contracts\Persistence\FinancialCategoryRepository;
use App\Modules\Financial\Application\Data\CreateEconomicEventInput;
use App\Modules\Financial\Application\Data\EconomicEventChanges;
use App\Modules\Financial\Application\Data\FinancialPage;
use App\Modules\Financial\Application\Data\FinancialQuery;
use App\Modules\Financial\Application\FinancialOperation;
use App\Modules\Financial\Domain\Entities\EconomicEvent;
use App\Modules\Financial\Domain\Entities\EconomicRecord;
use App\Modules\Financial\Domain\Entities\FinancialCommitment;
use App\Modules\Financial\Domain\FinancialStatus;
use App\Modules\Financial\Domain\ValueObjects\FinancialDate;
use App\Modules\Financial\Domain\ValueObjects\FinancialIdentity;
use App\Modules\Financial\Domain\ValueObjects\Money;
use App\SharedKernel\Application\Tenancy\TenantContext;

final readonly class ManageEconomicEvents
{
    public function __construct(private EconomicRecordRepository $events, private FinancialCategoryRepository $categories, private FinancialOperation $operation, private FinancialClock $clock, private FinancialIds $ids) {}

    public function create(TenantContext $context, CreateEconomicEventInput $input): EconomicRecord
    {
        return $this->operation->run($context, true, function () use ($context, $input) {
            $category = $this->categories->find($context, $input->categoryPublicId) ?? $this->operation->missing();
            if ($category->status !== FinancialStatus::Active) {
                $this->operation->conflict('financial.category_inactive', 'An active category is required.');
            }
            if ($category->nature !== $input->nature) {
                $this->operation->conflict('financial.category_nature', 'Category must match the economic nature.');
            }
            $now = $this->clock->now();
            $identity = fn () => new FinancialIdentity(null, $this->ids->generate(), $context->organizationId, 0, $now, $now);
            $event = new EconomicEvent($identity(), $input->description, $input->nature, new Money($input->amount, $input->currency), new FinancialDate($input->competenceDate), $category, $context->actorPublicId);
            $event->requireEligibleCategory();
            $commitment = new FinancialCommitment($identity(), $event, new Money($input->expectedAmount ?? $input->amount, $input->currency), new FinancialDate($input->dueDate));

            return $this->events->save($context, new EconomicRecord($event, [$commitment]));
        });
    }

    public function read(TenantContext $context, string $publicId): EconomicRecord
    {
        return $this->operation->run($context, false, fn () => $this->events->find($context, $publicId) ?? $this->operation->missing());
    }

    /** @return FinancialPage<EconomicRecord> */
    public function list(TenantContext $context, FinancialQuery $query): FinancialPage
    {
        return $this->operation->run($context, false, fn () => $this->events->page($context, $query));
    }

    public function update(TenantContext $context, string $publicId, EconomicEventChanges $changes, int $version): EconomicRecord
    {
        return $this->operation->run($context, true, function () use ($context, $publicId, $changes, $version) {
            $old = $this->events->find($context, $publicId) ?? $this->operation->missing();
            if ($old->event->identity->version !== $version) {
                $this->operation->conflict('financial.concurrent_update', 'The financial resource was changed by another operation.');
            }
            if ($old->lockedAt !== null) {
                $this->operation->conflict('financial.history_locked', 'Realized financial history cannot be updated directly.');
            }
            $category = $changes->categoryPublicId === null ? $old->event->category : ($this->categories->find($context, $changes->categoryPublicId) ?? $this->operation->missing());
            if ($category->nature !== $old->event->nature) {
                $this->operation->conflict('financial.category_nature', 'Category must match the economic nature.');
            }
            if ($category->identity->publicId !== $old->event->category->identity->publicId && $category->status !== FinancialStatus::Active) {
                $this->operation->conflict('financial.category_inactive', 'An active category is required.');
            }
            $now = $this->clock->now();
            $event = new EconomicEvent($old->event->identity->revised($now), $changes->description ?? $old->event->description, $old->event->nature, new Money($changes->amount ?? $old->event->amount->amount), new FinancialDate($changes->competenceDate ?? $old->event->competenceDate->value), $category, $old->event->createdBy);
            if ($category->identity->publicId !== $old->event->category->identity->publicId) {
                $event->requireEligibleCategory();
            }
            $affectsCommitment = ($changes->amount !== null && $event->amount->minor !== $old->event->amount->minor) || $changes->dueDate !== null || $changes->expectedAmount !== null;
            if (count($old->commitments) > 1 && $affectsCommitment) {
                $this->operation->conflict('financial.commitment_structure', 'Multiple commitments require an explicit lifecycle operation; automatic redistribution is unavailable.');
            }
            $children = [];
            foreach ($old->commitments as $child) {
                $amount = count($old->commitments) === 1 ? new Money($changes->expectedAmount ?? ($changes->amount ?? $child->expectedAmount->amount)) : $child->expectedAmount;
                $due = new FinancialDate($changes->dueDate ?? $child->dueDate->value);
                $changed = $amount->minor !== $child->expectedAmount->minor || $due->value !== $child->dueDate->value;
                $children[] = new FinancialCommitment($changed ? $child->identity->revised($now) : $child->identity, $event, $amount, $due);
            }

            return $this->events->save($context, new EconomicRecord($event, $children));
        });
    }
}
