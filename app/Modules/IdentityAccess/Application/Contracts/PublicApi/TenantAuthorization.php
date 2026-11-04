<?php

namespace App\Modules\IdentityAccess\Application\Contracts\PublicApi;

use App\SharedKernel\Application\Tenancy\TenantContext;

interface TenantAuthorization
{
    public function authorize(TenantContext $context, TenantCapability $capability): void;
}
