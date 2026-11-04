<?php

use App\Modules\IdentityAccess\Infrastructure\Providers\IdentityAccessServiceProvider;
use App\Modules\Organizations\Infrastructure\Providers\OrganizationsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    IdentityAccessServiceProvider::class,
    AppServiceProvider::class,
    OrganizationsServiceProvider::class,
];
