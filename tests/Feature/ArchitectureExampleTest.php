<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Architecture\Application\Contracts\AvailabilityProbe;
use Tests\Fixtures\Architecture\Application\ReadProbeStatus;
use Tests\Fixtures\Architecture\Domain\ProbeStatus;
use Tests\Fixtures\Architecture\Infrastructure\InMemoryAvailabilityProbe;
use Tests\Fixtures\Architecture\Infrastructure\Providers\ArchitectureExampleServiceProvider;
use Tests\Fixtures\Architecture\Interfaces\Http\ProbeStatusController;
use Tests\TestCase;

class ArchitectureExampleTest extends TestCase
{
    public function test_provider_resolves_all_layers_through_an_http_entry_point(): void
    {
        $this->app->register(ArchitectureExampleServiceProvider::class);
        Route::get('/_architecture/probe', ProbeStatusController::class);

        $this->assertInstanceOf(InMemoryAvailabilityProbe::class, $this->app->make(AvailabilityProbe::class));

        $this->get('/_architecture/probe')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['status' => 'available']);
    }

    public function test_use_case_accepts_a_replacement_adapter_without_changes(): void
    {
        $this->app->register(ArchitectureExampleServiceProvider::class);
        $this->app->instance(AvailabilityProbe::class, new class implements AvailabilityProbe
        {
            public function status(): ProbeStatus
            {
                return ProbeStatus::Unavailable;
            }
        });

        $this->assertSame(ProbeStatus::Unavailable, $this->app->make(ReadProbeStatus::class)->handle());
    }
}
