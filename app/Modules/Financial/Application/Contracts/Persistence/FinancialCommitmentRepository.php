<?php

namespace App\Modules\Financial\Application\Contracts\Persistence;

use App\Modules\Financial\Application\Data\FinancialPage;
use App\Modules\Financial\Application\Data\FinancialQuery;
use App\Modules\Financial\Domain\Entities\FinancialCommitment;
use App\SharedKernel\Application\Tenancy\TenantContext;

interface FinancialCommitmentRepository
{
    public function find(TenantContext $context, string $publicId): ?FinancialCommitment;

    /** @return FinancialPage<FinancialCommitment> */
    public function page(TenantContext $context, FinancialQuery $query): FinancialPage;
}
