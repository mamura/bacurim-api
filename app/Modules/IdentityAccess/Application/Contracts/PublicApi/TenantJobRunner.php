<?php

namespace App\Modules\IdentityAccess\Application\Contracts\PublicApi;

use App\SharedKernel\Application\Tenancy\TenantContext;

interface TenantJobRunner
{
    /**
     * @template T
     *
     * @param  callable(TenantContext):T  $operation
     * @return T
     */
    public function run(TenantJobIdentity $identity, TenantCapability $required, callable $operation): mixed;
}
