<?php

namespace App\Modules\Financial\Application\UseCases;

use App\Modules\Financial\Application\Contracts\FinancialClock;
use App\Modules\Financial\Application\Data\AccountPosition;
use App\SharedKernel\Application\Tenancy\TenantContext;

/** API-M3 will supply a ledger-derived position, including an approved opening operation. */
final readonly class GetFinancialAccountPosition
{
    public function __construct(private ManageFinancialAccounts $accounts, private FinancialClock $clock) {}

    public function execute(TenantContext $context, string $publicId): AccountPosition
    {
        $account = $this->accounts->read($context, $publicId);

        return new AccountPosition($account->identity->publicId, 'BRL', null, $this->clock->now(), 'unavailable');
    }
}
