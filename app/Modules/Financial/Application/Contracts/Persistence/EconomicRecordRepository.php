<?php

namespace App\Modules\Financial\Application\Contracts\Persistence;

use App\Modules\Financial\Application\Data\FinancialPage;
use App\Modules\Financial\Application\Data\FinancialQuery;
use App\Modules\Financial\Domain\Entities\EconomicRecord;
use App\SharedKernel\Application\Tenancy\TenantContext;

interface EconomicRecordRepository
{
    public function save(TenantContext $context, EconomicRecord $record): EconomicRecord;

    public function find(TenantContext $context, string $publicId): ?EconomicRecord;

    /** @return FinancialPage<EconomicRecord> */
    public function page(TenantContext $context, FinancialQuery $query): FinancialPage;
}
