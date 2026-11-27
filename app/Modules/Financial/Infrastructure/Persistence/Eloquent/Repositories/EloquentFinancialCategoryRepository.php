<?php

namespace App\Modules\Financial\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Financial\Application\Contracts\Persistence\FinancialCategoryRepository;
use App\Modules\Financial\Application\Data\FinancialPage;
use App\Modules\Financial\Application\Data\FinancialQuery;
use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Domain\Entities\FinancialCategory;
use App\Modules\Financial\Domain\FinancialStatus;
use App\Modules\Financial\Domain\ValueObjects\FinancialText;
use App\Modules\Financial\Infrastructure\Persistence\Eloquent\Models\FinancialCategoryRecord;
use App\Modules\Financial\Infrastructure\Persistence\FinancialStorage;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final readonly class EloquentFinancialCategoryRepository implements FinancialCategoryRepository
{
    public function __construct(private FinancialStorage $storage) {}

    public function save(TenantContext $context, FinancialCategory $record): FinancialCategory
    {
        $this->storage->assertScope($context, $record->identity);

        return $this->storage->guard(fn () => DB::transaction(function () use ($context, $record) {
            $identity = $record->identity;
            $before = null;
            if ($identity->id === null) {
                $row = new FinancialCategoryRecord;
                $row->forceFill(['organization_id' => $context->organizationId, 'public_id' => $identity->publicId, 'created_at' => $identity->createdAt]);
            } else {
                $row = FinancialCategoryRecord::query()->where('organization_id', $context->organizationId)->where('public_id', $identity->publicId)->lockForUpdate()->first();
                $before = $row?->getAttributes();
                $this->storage->assertVersion($before, $identity);
                $row = $row ?? throw new \LogicException('Missing persisted category.');
            }
            $row->forceFill(['name' => $record->name, 'nature' => $record->nature->value, 'status' => $record->status->value, 'parent_public_id' => $record->parentPublicId, 'default_key' => $record->defaultKey, 'version' => $identity->version + 1, 'updated_at' => $identity->updatedAt]);
            $row->save();
            $after = $row->refresh()->getAttributes();
            $this->storage->audit($context, 'category', $identity->publicId, $before, $after, $identity->updatedAt);

            return $this->map($after);
        }));
    }

    public function find(TenantContext $context, string $publicId): ?FinancialCategory
    {
        FinancialText::uuid($publicId);

        return $this->storage->guard(function () use ($context, $publicId) {
            $row = FinancialCategoryRecord::query()->where('organization_id', $context->organizationId)->where('public_id', strtolower($publicId))->first();

            return $row === null ? null : $this->map($row->getAttributes());
        });
    }

    public function page(TenantContext $context, FinancialQuery $query): FinancialPage
    {
        $query->assertAllowed(['status', 'nature'], ['name', 'nature', 'status', 'created_at']);

        return $this->storage->guard(function () use ($context, $query) {
            $rows = FinancialCategoryRecord::query()->where('organization_id', $context->organizationId);
            foreach ($query->filters as $name => $value) {
                if ($name !== 'status' || $value !== 'all') {
                    $rows->where($name, $value);
                }
            }
            $total = (clone $rows)->count();
            foreach (explode(',', $query->sort) as $sort) {
                $rows->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc');
            }
            $items = $rows->orderBy('id')->offset(($query->page - 1) * $query->perPage)->limit($query->perPage)->get()->map(fn ($row) => $this->map($row->getAttributes()))->all();

            return new FinancialPage($items, $total);
        });
    }

    public function findDefault(TenantContext $context, string $key): ?FinancialCategory
    {
        return $this->storage->guard(function () use ($context, $key) {
            $row = FinancialCategoryRecord::query()->where('organization_id', $context->organizationId)->where('default_key', $key)->first();

            return $row === null ? null : $this->map($row->getAttributes());
        });
    }

    public function hasChildren(TenantContext $context, string $publicId): bool
    {
        return $this->storage->guard(fn () => FinancialCategoryRecord::query()->where('organization_id', $context->organizationId)->where('parent_public_id', $publicId)->exists());
    }

    public function hasEvents(TenantContext $context, string $publicId): bool
    {
        // Category delivery precedes the economic event migration; no history can exist before it.
        if (! Schema::hasTable('economic_events')) {
            return false;
        }

        return $this->storage->guard(fn () => DB::table('economic_events')->where('organization_id', $context->organizationId)->where('category_public_id', $publicId)->exists());
    }

    /** @param array<string,mixed> $row */
    private function map(array $row): FinancialCategory
    {
        return new FinancialCategory($this->storage->identity($row), (string) $row['name'], EconomicNature::from((string) $row['nature']), FinancialStatus::from((string) $row['status']), $row['parent_public_id'] === null ? null : (string) $row['parent_public_id'], $row['default_key'] === null ? null : (string) $row['default_key']);
    }
}
