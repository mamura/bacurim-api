<?php

namespace App\Modules\Financial\Application\UseCases;

use App\Modules\Financial\Application\Contracts\FinancialClock;
use App\Modules\Financial\Application\Contracts\FinancialIds;
use App\Modules\Financial\Application\Contracts\Persistence\FinancialAccountRepository;
use App\Modules\Financial\Application\Data\AccountChanges;
use App\Modules\Financial\Application\Data\FinancialPage;
use App\Modules\Financial\Application\Data\FinancialQuery;
use App\Modules\Financial\Application\FinancialOperation;
use App\Modules\Financial\Domain\AccountType;
use App\Modules\Financial\Domain\Entities\FinancialAccount;
use App\Modules\Financial\Domain\ValueObjects\FinancialIdentity;
use App\SharedKernel\Application\Tenancy\TenantContext;

final readonly class ManageFinancialAccounts
{
    public function __construct(private FinancialAccountRepository $accounts, private FinancialOperation $operation, private FinancialClock $clock, private FinancialIds $ids) {}

    public function create(TenantContext $context, string $name, AccountType $type): FinancialAccount
    {
        return $this->operation->run($context, true, function () use ($context, $name, $type) {
            $now = $this->clock->now();

            return $this->accounts->save($context, new FinancialAccount(new FinancialIdentity(null, $this->ids->generate(), $context->organizationId, 0, $now, $now), $name, $type));
        });
    }

    public function read(TenantContext $context, string $id): FinancialAccount
    {
        return $this->operation->run($context, false, fn () => $this->accounts->find($context, $id) ?? $this->operation->missing());
    }

    /** @return FinancialPage<FinancialAccount> */
    public function list(TenantContext $context, FinancialQuery $query): FinancialPage
    {
        return $this->operation->run($context, false, fn () => $this->accounts->page($context, $query));
    }

    public function update(TenantContext $context, string $id, AccountChanges $changes, int $version): FinancialAccount
    {
        return $this->operation->run($context, true, function () use ($context, $id, $changes, $version) {
            $old = $this->accounts->find($context, $id) ?? $this->operation->missing();
            if ($old->identity->version !== $version) {
                $this->operation->conflict('financial.concurrent_update', 'The financial resource was changed by another operation.');
            }

            return $this->accounts->save($context, $old->revise($changes->name ?? $old->name, $changes->type ?? $old->type, $changes->status ?? $old->status, $this->clock->now()));
        });
    }
}
