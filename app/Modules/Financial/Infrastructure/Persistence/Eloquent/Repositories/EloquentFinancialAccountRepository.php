<?php

namespace App\Modules\Financial\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Financial\Application\Contracts\Persistence\FinancialAccountRepository;
use App\Modules\Financial\Application\Data\FinancialPage;
use App\Modules\Financial\Application\Data\FinancialQuery;
use App\Modules\Financial\Domain\AccountType;
use App\Modules\Financial\Domain\Entities\FinancialAccount;
use App\Modules\Financial\Domain\FinancialStatus;
use App\Modules\Financial\Domain\ValueObjects\FinancialText;
use App\Modules\Financial\Infrastructure\Persistence\Eloquent\Models\FinancialAccountRecord;
use App\Modules\Financial\Infrastructure\Persistence\FinancialStorage;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class EloquentFinancialAccountRepository implements FinancialAccountRepository
{
    public function __construct(private FinancialStorage $storage) {}

    public function save(TenantContext $context, FinancialAccount $record): FinancialAccount
    {
        $this->storage->assertScope($context, $record->identity);

        return $this->storage->guard(fn () => DB::transaction(function () use ($context, $record) {
            $identity = $record->identity;
            $before = null;
            if ($identity->id === null) {
                $row = new FinancialAccountRecord;
                $row->forceFill(['organization_id' => $context->organizationId, 'public_id' => $identity->publicId, 'created_at' => $identity->createdAt]);
            } else {
                $row = FinancialAccountRecord::query()->where('organization_id', $context->organizationId)->where('public_id', $identity->publicId)->lockForUpdate()->first();
                $before = $row?->getAttributes();
                $this->storage->assertVersion($before, $identity);
                $row = $row ?? throw new \LogicException('Missing persisted account.');
            }
            $row->forceFill(['name' => $record->name, 'type' => $record->type->value, 'status' => $record->status->value, 'version' => $identity->version + 1, 'updated_at' => $identity->updatedAt]);
            $row->save();
            $after = $row->refresh()->getAttributes();
            $this->storage->audit($context, 'account', $identity->publicId, $before, $after, $identity->updatedAt);

            return $this->map($after);
        }));
    }

    public function find(TenantContext $context, string $publicId): ?FinancialAccount
    {
        FinancialText::uuid($publicId);

        return $this->storage->guard(function () use ($context, $publicId) {
            $row = FinancialAccountRecord::query()->where('organization_id', $context->organizationId)->where('public_id', strtolower($publicId))->first();

            return $row === null ? null : $this->map($row->getAttributes());
        });
    }

    public function page(TenantContext $context, FinancialQuery $query): FinancialPage
    {
        $query->assertAllowed(['status', 'type'], ['name', 'type', 'status', 'created_at']);

        return $this->storage->guard(function () use ($context, $query) {
            $rows = FinancialAccountRecord::query()->where('organization_id', $context->organizationId);
            foreach ($query->filters as $name => $value) {
                $rows->where($name, $value);
            }
            $total = (clone $rows)->count();
            foreach (explode(',', $query->sort) as $sort) {
                $rows->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc');
            }
            $items = $rows->orderBy('id')->offset(($query->page - 1) * $query->perPage)->limit($query->perPage)->get()->map(fn ($row) => $this->map($row->getAttributes()))->all();

            return new FinancialPage($items, $total);
        });
    }

    /** @param array<string,mixed> $row */
    private function map(array $row): FinancialAccount
    {
        return new FinancialAccount($this->storage->identity($row), (string) $row['name'], AccountType::from((string) $row['type']), FinancialStatus::from((string) $row['status']));
    }
}
