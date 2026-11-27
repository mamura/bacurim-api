<?php

namespace App\Modules\Financial\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Financial\Application\Contracts\Persistence\EconomicRecordRepository;
use App\Modules\Financial\Application\Contracts\Persistence\FinancialCommitmentRepository;
use App\Modules\Financial\Application\Data\FinancialPage;
use App\Modules\Financial\Application\Data\FinancialQuery;
use App\Modules\Financial\Domain\Entities\FinancialCommitment;
use App\Modules\Financial\Domain\ValueObjects\FinancialText;
use App\Modules\Financial\Infrastructure\Persistence\EconomicHydrator;
use App\Modules\Financial\Infrastructure\Persistence\Eloquent\Models\FinancialCommitmentRecord;
use App\Modules\Financial\Infrastructure\Persistence\FinancialStorage;
use App\SharedKernel\Application\Tenancy\TenantContext;
use LogicException;

final readonly class EloquentFinancialCommitmentRepository implements FinancialCommitmentRepository
{
    public function __construct(private EconomicRecordRepository $events, private EconomicHydrator $hydrator, private FinancialStorage $storage) {}

    public function find(TenantContext $context, string $publicId): ?FinancialCommitment
    {
        FinancialText::uuid($publicId);

        return $this->storage->guard(function () use ($context, $publicId) {
            $row = FinancialCommitmentRecord::query()->where('organization_id', $context->organizationId)->where('public_id', strtolower($publicId))->first();

            return $row === null ? null : $this->map($context, $row);
        });
    }

    public function page(TenantContext $context, FinancialQuery $query): FinancialPage
    {
        $query->assertAllowed(['nature', 'due_from', 'due_to', 'event_public_id'], ['due_date', 'expected_amount', 'created_at']);

        return $this->storage->guard(function () use ($context, $query) {
            $rows = FinancialCommitmentRecord::query()->where('organization_id', $context->organizationId);
            foreach ($query->filters as $key => $value) {
                if ($key === 'due_from') {
                    $rows->where('due_date', '>=', $value);
                } elseif ($key === 'due_to') {
                    $rows->where('due_date', '<=', $value);
                } else {
                    $rows->where($key, $value);
                }
            }
            $total = (clone $rows)->count();
            foreach (explode(',', $query->sort) as $sort) {
                $rows->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc');
            }

            return new FinancialPage($rows->orderBy('id')->offset(($query->page - 1) * $query->perPage)->limit($query->perPage)->get()->map(fn ($row) => $this->map($context, $row))->all(), $total);
        });
    }

    private function map(TenantContext $context, FinancialCommitmentRecord $row): FinancialCommitment
    {
        $record = $this->events->find($context, (string) $row->getAttribute('event_public_id')) ?? throw new LogicException('Stored commitment origin missing.');

        return $this->hydrator->commitment($row->getAttributes(), $record->event);
    }
}
