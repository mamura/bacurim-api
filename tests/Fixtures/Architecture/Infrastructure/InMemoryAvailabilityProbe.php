<?php

namespace Tests\Fixtures\Architecture\Infrastructure;

use Tests\Fixtures\Architecture\Application\Contracts\AvailabilityProbe;
use Tests\Fixtures\Architecture\Domain\ProbeStatus;

final class InMemoryAvailabilityProbe implements AvailabilityProbe
{
    public function status(): ProbeStatus
    {
        return ProbeStatus::Available;
    }
}
