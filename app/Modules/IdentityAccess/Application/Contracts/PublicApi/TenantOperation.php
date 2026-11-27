<?php

namespace App\Modules\IdentityAccess\Application\Contracts\PublicApi;

use App\SharedKernel\Application\Tenancy\TenantContext;

interface TenantOperation
{
    /**
     * @template T
     *
     * @param  callable():T  $operation
     * @return T
     */
    public function run(TenantContext $context, TenantCapability $capability, callable $operation): mixed;
}
