<?php

namespace Tests\Fixtures\Architecture\Application\Contracts;

use Tests\Fixtures\Architecture\Domain\ProbeStatus;

interface AvailabilityProbe
{
    public function status(): ProbeStatus;
}
