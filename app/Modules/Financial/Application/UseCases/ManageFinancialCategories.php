<?php

namespace App\Modules\Financial\Application\UseCases;

use App\Modules\Financial\Application\Contracts\FinancialClock;
use App\Modules\Financial\Application\Contracts\FinancialIds;
use App\Modules\Financial\Application\Contracts\Persistence\FinancialCategoryRepository;
use App\Modules\Financial\Application\Data\CategoryChanges;
use App\Modules\Financial\Application\Data\FinancialPage;
use App\Modules\Financial\Application\Data\FinancialQuery;
use App\Modules\Financial\Application\FinancialOperation;
use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Domain\Entities\FinancialCategory;
use App\Modules\Financial\Domain\FinancialStatus;
use App\Modules\Financial\Domain\ValueObjects\FinancialIdentity;
use App\SharedKernel\Application\Tenancy\TenantContext;

final readonly class ManageFinancialCategories
{
    public function __construct(private FinancialCategoryRepository $categories, private FinancialOperation $operation, private FinancialClock $clock, private FinancialIds $ids) {}

    public function create(TenantContext $context, string $name, EconomicNature $nature, ?string $parent = null): FinancialCategory
    {
        return $this->operation->run($context, true, function () use ($context, $name, $nature, $parent) {
            $now = $this->clock->now();
            $id = $this->ids->generate();
            $this->assertParent($context, $id, $nature, $parent);

            return $this->categories->save($context, new FinancialCategory(new FinancialIdentity(null, $id, $context->organizationId, 0, $now, $now), $name, $nature, parentPublicId: $parent));
        });
    }

    public function read(TenantContext $context, string $id): FinancialCategory
    {
        return $this->operation->run($context, false, fn () => $this->categories->find($context, $id) ?? $this->operation->missing());
    }

    /** @return FinancialPage<FinancialCategory> */
    public function list(TenantContext $context, FinancialQuery $query): FinancialPage
    {
        return $this->operation->run($context, false, fn () => $this->categories->page($context, $query));
    }

    public function update(TenantContext $context, string $id, CategoryChanges $changes, int $version): FinancialCategory
    {
        return $this->operation->run($context, true, function () use ($context, $id, $changes, $version) {
            $old = $this->categories->find($context, $id) ?? $this->operation->missing();
            if ($old->identity->version !== $version) {
                $this->operation->conflict('financial.concurrent_update', 'The financial resource was changed by another operation.');
            }
            $nature = $changes->nature ?? $old->nature;
            if ($nature !== $old->nature && ($this->categories->hasEvents($context, $id) || $this->categories->hasChildren($context, $id))) {
                $this->operation->conflict('financial.category_in_use', 'The category classification is used by history or children.');
            }
            $parent = $changes->changeParent ? $changes->parentPublicId : $old->parentPublicId;
            if ($changes->changeParent || $nature !== $old->nature) {
                $this->assertParent($context, $old->identity->publicId, $nature, $parent);
            }

            return $this->categories->save($context, new FinancialCategory($old->identity->revised($this->clock->now()), $changes->name ?? $old->name, $nature, $changes->status ?? $old->status, $parent, $old->defaultKey));
        });
    }

    private function assertParent(TenantContext $context, string $id, EconomicNature $nature, ?string $parent): void
    {
        $visited = [$id];
        $initial = true;
        while ($parent !== null) {
            if (in_array(strtolower($parent), $visited, true)) {
                $this->operation->conflict('financial.category_cycle', 'Category hierarchy cannot contain cycles.');
            }
            $visited[] = strtolower($parent);
            $category = $this->categories->find($context, $parent) ?? $this->operation->missing();
            if ($category->nature !== $nature) {
                $this->operation->conflict('financial.category_nature', 'Parent and child must have the same economic nature.');
            }
            if ($initial && $category->status !== FinancialStatus::Active) {
                $this->operation->conflict('financial.category_inactive', 'An active parent category is required.');
            }
            $parent = $category->parentPublicId;
            $initial = false;
        }
    }
}
