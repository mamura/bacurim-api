<?php

namespace Tests\Integration\Security;

use App\Modules\Financial\Application\Data\AccountChanges;
use App\Modules\Financial\Application\UseCases\ManageFinancialAccounts;
use App\Modules\Financial\Domain\AccountType;
use App\Modules\Financial\Domain\FinancialStatus;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Security\TenantIsolationContract;

/** Active-catalog adapter; inactive history remains accessible through the normal read API. */
#[Group('security')]
class FinancialAccountIsolationContractTest extends TenantIsolationContract
{
    protected function prepareStorage(): void {}

    protected function createResource(TenantContext $context, string $value): string
    {
        return app(ManageFinancialAccounts::class)->create($context, $value, AccountType::Cash)->identity->publicId;
    }

    protected function readResource(TenantContext $context, string $id): ?string
    {
        try {
            $record = app(ManageFinancialAccounts::class)->read($context, $id);

            return $record->status === FinancialStatus::Active ? $record->name : null;
        } catch (ApplicationFailure $failure) {
            if ($failure->kind === FailureKind::NotFound) {
                return null;
            }throw $failure;
        }
    }

    protected function updateResource(TenantContext $context, string $id, string $value): void
    {
        $cases = app(ManageFinancialAccounts::class);
        $old = $cases->read($context, $id);
        $cases->update($context, $id, new AccountChanges(name: $value), $old->identity->version);
    }

    protected function archiveResource(TenantContext $context, string $id): void
    {
        $cases = app(ManageFinancialAccounts::class);
        $old = $cases->read($context, $id);
        $cases->update($context, $id, new AccountChanges(status: FinancialStatus::Inactive), $old->identity->version);
    }
}
