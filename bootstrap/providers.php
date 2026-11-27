<?php

use App\Modules\Financial\Infrastructure\Providers\FinancialServiceProvider;
use App\Modules\IdentityAccess\Infrastructure\Providers\IdentityAccessServiceProvider;
use App\Modules\Organizations\Infrastructure\Providers\OrganizationsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    FinancialServiceProvider::class,
    IdentityAccessServiceProvider::class,
    AppServiceProvider::class,
    OrganizationsServiceProvider::class,
];
