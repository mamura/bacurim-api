<?php

namespace App\Modules\IdentityAccess\Application;

use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantCapability;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantOperation;
use App\SharedKernel\Application\Tenancy\TenantContext;

final readonly class AuthorizedTenantOperation implements TenantOperation
{
    public function __construct(private TenantExecution $execution) {}

    public function run(TenantContext $context, TenantCapability $capability, callable $operation): mixed
    {
        return $this->execution->transaction->run(function () use ($context, $capability, $operation) {
            $this->execution->forContext($context, $capability);

            return $operation();
        });
    }
}
