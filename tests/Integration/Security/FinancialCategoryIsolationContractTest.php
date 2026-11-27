<?php

namespace Tests\Integration\Security;

use App\Modules\Financial\Application\Data\CategoryChanges;
use App\Modules\Financial\Application\UseCases\ManageFinancialCategories;
use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Domain\FinancialStatus;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Security\TenantIsolationContract;

/** Active-catalog adapter; inactive history remains accessible through the normal read API. */
#[Group('security')]
class FinancialCategoryIsolationContractTest extends TenantIsolationContract
{
    protected function prepareStorage(): void {}

    protected function createResource(TenantContext $context, string $value): string
    {
        return app(ManageFinancialCategories::class)->create($context, $value, EconomicNature::Income)->identity->publicId;
    }

    protected function readResource(TenantContext $context, string $id): ?string
    {
        try {
            $record = app(ManageFinancialCategories::class)->read($context, $id);

            return $record->status === FinancialStatus::Active ? $record->name : null;
        } catch (ApplicationFailure $failure) {
            if ($failure->kind === FailureKind::NotFound) {
                return null;
            }throw $failure;
        }
    }

    protected function updateResource(TenantContext $context, string $id, string $value): void
    {
        $cases = app(ManageFinancialCategories::class);
        $old = $cases->read($context, $id);
        $cases->update($context, $id, new CategoryChanges(name: $value), $old->identity->version);
    }

    protected function archiveResource(TenantContext $context, string $id): void
    {
        $cases = app(ManageFinancialCategories::class);
        $old = $cases->read($context, $id);
        $cases->update($context, $id, new CategoryChanges(status: FinancialStatus::Inactive), $old->identity->version);
    }
}
