<?php

namespace App\Modules\Financial\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Financial\Application\Contracts\Persistence\EconomicRecordRepository;
use App\Modules\Financial\Application\Contracts\Persistence\FinancialCategoryRepository;
use App\Modules\Financial\Application\Data\FinancialPage;
use App\Modules\Financial\Application\Data\FinancialQuery;
use App\Modules\Financial\Domain\Entities\EconomicRecord;
use App\Modules\Financial\Domain\FinancialStatus;
use App\Modules\Financial\Domain\ValueObjects\FinancialText;
use App\Modules\Financial\Infrastructure\Persistence\EconomicHydrator;
use App\Modules\Financial\Infrastructure\Persistence\Eloquent\Models\EconomicEventRecord;
use App\Modules\Financial\Infrastructure\Persistence\Eloquent\Models\FinancialCommitmentRecord;
use App\Modules\Financial\Infrastructure\Persistence\FinancialStorage;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class EloquentEconomicRecordRepository implements EconomicRecordRepository
{
    public function __construct(private FinancialCategoryRepository $categories, private FinancialStorage $storage, private EconomicHydrator $hydrator) {}

    public function save(TenantContext $context, EconomicRecord $record): EconomicRecord
    {
        $identity = $record->event->identity;
        $this->storage->assertScope($context, $identity);

        return $this->storage->guard(fn () => DB::transaction(function () use ($context, $record, $identity) {
            $before = null;
            if ($identity->id === null) {
                if ($record->lockedAt !== null) {
                    $this->conflict('financial.history_locked', 'New events cannot contain realized history.');
                }
                if ($record->event->createdBy !== $context->actorPublicId) {
                    throw new LogicException('Event author must be the authorized actor.');
                }
                $row = new EconomicEventRecord;
                $row->forceFill(['organization_id' => $context->organizationId, 'public_id' => $identity->publicId, 'created_at' => $identity->createdAt, 'created_by' => $record->event->createdBy, 'origin' => 'manual']);
            } else {
                $row = EconomicEventRecord::query()->where('organization_id', $context->organizationId)->where('public_id', $identity->publicId)->lockForUpdate()->first();
                $before = $row?->getAttributes();
                $this->storage->assertVersion($before, $identity);
                $row = $row ?? throw new LogicException('Missing event.');
                if ($row->getAttribute('locked_at') !== null) {
                    $this->conflict('financial.history_locked', 'Realized financial history cannot be updated directly.');
                }
                if ($row->getAttribute('nature') !== $record->event->nature->value || $row->getAttribute('created_by') !== $record->event->createdBy) {
                    $this->conflict('financial.event_identity', 'Economic nature and origin cannot be reassigned.');
                }
                $oldIds = FinancialCommitmentRecord::query()->where('organization_id', $context->organizationId)->where('event_public_id', $identity->publicId)->orderBy('public_id')->pluck('public_id')->all();
                $newIds = array_map(fn ($c) => $c->identity->publicId, $record->commitments);
                sort($newIds);
                if ($oldIds !== $newIds) {
                    $this->conflict('financial.commitment_structure', 'Commitment structure cannot be replaced through event updates.');
                }
            }
            $category = $this->categories->find($context, $record->event->category->identity->publicId);
            if ($category === null) {
                throw new ApplicationFailure(FailureKind::NotFound, 'financial.not_found', 'Financial resource not found.');
            }
            if (($identity->id === null || ($before['category_public_id'] ?? null) !== $category->identity->publicId) && $category->status !== FinancialStatus::Active) {
                $this->conflict('financial.category_inactive', 'An active category is required for creation or reclassification.');
            }
            if ($category->nature !== $record->event->nature) {
                $this->conflict('financial.category_nature', 'Category must match the economic nature.');
            }
            $row->forceFill(['description' => $record->event->description, 'nature' => $record->event->nature->value, 'amount' => $record->event->amount->amount, 'currency' => $record->event->amount->currency, 'competence_date' => $record->event->competenceDate->value, 'category_public_id' => $category->identity->publicId, 'version' => $identity->version + 1, 'updated_at' => $identity->updatedAt]);
            $row->save();
            foreach ($record->commitments as $commitment) {
                $ci = $commitment->identity;
                $this->storage->assertScope($context, $ci);
                $previous = null;
                if ($ci->id === null) {
                    if ($identity->id !== null) {
                        $this->conflict('financial.commitment_structure', 'Event updates cannot create new commitments.');
                    }
                    $child = new FinancialCommitmentRecord;
                    $child->forceFill(['organization_id' => $context->organizationId, 'public_id' => $ci->publicId, 'event_public_id' => $identity->publicId, 'created_at' => $ci->createdAt]);
                } else {
                    $child = FinancialCommitmentRecord::query()->where('organization_id', $context->organizationId)->where('event_public_id', $identity->publicId)->where('public_id', $ci->publicId)->lockForUpdate()->first();
                    $previous = $child?->getAttributes();
                    $this->storage->assertVersion($previous, $ci);
                    $child = $child ?? throw new LogicException('Missing commitment.');
                    if ($child->getAttribute('status') !== 'pending') {
                        $this->conflict('financial.history_locked', 'Only pending commitments are editable.');
                    }
                    if ($child->getAttribute('expected_amount') === $commitment->expectedAmount->amount && $child->getAttribute('due_date') === $commitment->dueDate->value) {
                        continue;
                    }
                }
                $child->forceFill(['nature' => $commitment->nature->value, 'expected_amount' => $commitment->expectedAmount->amount, 'currency' => $commitment->expectedAmount->currency, 'due_date' => $commitment->dueDate->value, 'status' => 'pending', 'version' => $ci->version + 1, 'updated_at' => $ci->updatedAt]);
                $child->save();
                $this->storage->audit($context, 'commitment', $ci->publicId, $previous, $child->refresh()->getAttributes(), $ci->updatedAt);
            }
            $this->storage->audit($context, 'event', $identity->publicId, $before, $row->refresh()->getAttributes(), $identity->updatedAt);

            return $this->find($context, $identity->publicId) ?? throw new LogicException('Saved aggregate missing.');
        }));
    }

    public function find(TenantContext $context, string $publicId): ?EconomicRecord
    {
        FinancialText::uuid($publicId);

        return $this->storage->guard(function () use ($context, $publicId) {
            $row = EconomicEventRecord::query()->where('organization_id', $context->organizationId)->where('public_id', strtolower($publicId))->first();

            return $row === null ? null : $this->map($context, $row);
        });
    }

    public function page(TenantContext $context, FinancialQuery $query): FinancialPage
    {
        $query->assertAllowed(['nature', 'competence_from', 'competence_to', 'category_public_id'], ['competence_date', 'amount', 'description', 'created_at']);

        return $this->storage->guard(function () use ($context, $query) {
            $rows = EconomicEventRecord::query()->where('organization_id', $context->organizationId);
            foreach ($query->filters as $key => $value) {
                if ($key === 'competence_from') {
                    $rows->where('competence_date', '>=', $value);
                } elseif ($key === 'competence_to') {
                    $rows->where('competence_date', '<=', $value);
                } else {
                    $rows->where($key, $value);
                }
            }
            $total = (clone $rows)->count();
            foreach (explode(',', $query->sort) as $sort) {
                $rows->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc');
            }
            $items = $rows->orderBy('id')->offset(($query->page - 1) * $query->perPage)->limit($query->perPage)->get()->map(fn ($row) => $this->map($context, $row))->all();

            return new FinancialPage($items, $total);
        });
    }

    private function map(TenantContext $context, EconomicEventRecord $row): EconomicRecord
    {
        $category = $this->categories->find($context, (string) $row->getAttribute('category_public_id')) ?? throw new LogicException('Stored event category missing.');
        $event = $this->hydrator->event($row->getAttributes(), $category);
        $commitments = FinancialCommitmentRecord::query()->where('organization_id', $context->organizationId)->where('event_public_id', $event->identity->publicId)->orderBy('id')->get()->map(fn ($c) => $this->hydrator->commitment($c->getAttributes(), $event))->all();
        $locked = $row->getAttribute('locked_at');

        return new EconomicRecord($event, $commitments, $locked === null ? null : new DateTimeImmutable((string) $locked));
    }

    private function conflict(string $code, string $message): never
    {
        throw new ApplicationFailure(FailureKind::Conflict, $code, $message);
    }
}
