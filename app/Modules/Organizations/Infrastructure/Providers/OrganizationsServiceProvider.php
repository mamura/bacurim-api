<?php

namespace App\Modules\Organizations\Infrastructure\Providers;

use App\Modules\Organizations\Application\Contracts\Persistence\OrganizationRepository;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Repositories\EloquentOrganizationRepository;
use Illuminate\Support\ServiceProvider;

final class OrganizationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrganizationRepository::class, EloquentOrganizationRepository::class);
    }
}
