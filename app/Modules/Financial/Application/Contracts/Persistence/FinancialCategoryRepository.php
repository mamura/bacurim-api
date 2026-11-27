<?php

namespace App\Modules\Financial\Application\Contracts\Persistence;

use App\Modules\Financial\Application\Data\FinancialPage;
use App\Modules\Financial\Application\Data\FinancialQuery;
use App\Modules\Financial\Domain\Entities\FinancialCategory;
use App\SharedKernel\Application\Tenancy\TenantContext;

interface FinancialCategoryRepository
{
    public function save(TenantContext $context, FinancialCategory $record): FinancialCategory;

    public function find(TenantContext $context, string $publicId): ?FinancialCategory;

    /** @return FinancialPage<FinancialCategory> */
    public function page(TenantContext $context, FinancialQuery $query): FinancialPage;

    public function findDefault(TenantContext $context, string $key): ?FinancialCategory;

    public function hasChildren(TenantContext $context, string $publicId): bool;

    public function hasEvents(TenantContext $context, string $publicId): bool;
}
