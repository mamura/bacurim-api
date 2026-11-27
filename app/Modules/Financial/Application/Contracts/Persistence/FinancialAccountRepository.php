<?php

namespace App\Modules\Financial\Application\Contracts\Persistence;

use App\Modules\Financial\Application\Data\FinancialPage;
use App\Modules\Financial\Application\Data\FinancialQuery;
use App\Modules\Financial\Domain\Entities\FinancialAccount;
use App\SharedKernel\Application\Tenancy\TenantContext;

interface FinancialAccountRepository
{
    public function save(TenantContext $context, FinancialAccount $record): FinancialAccount;

    public function find(TenantContext $context, string $publicId): ?FinancialAccount;

    /** @return FinancialPage<FinancialAccount> */
    public function page(TenantContext $context, FinancialQuery $query): FinancialPage;
}
