<?php

namespace Tests\Fixtures\Architecture\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Tests\Fixtures\Architecture\Application\Contracts\AvailabilityProbe;
use Tests\Fixtures\Architecture\Infrastructure\InMemoryAvailabilityProbe;

final class ArchitectureExampleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AvailabilityProbe::class, InMemoryAvailabilityProbe::class);
    }
}
