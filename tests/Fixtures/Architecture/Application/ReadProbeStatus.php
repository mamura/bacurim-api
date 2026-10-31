<?php

namespace Tests\Fixtures\Architecture\Application;

use Tests\Fixtures\Architecture\Application\Contracts\AvailabilityProbe;
use Tests\Fixtures\Architecture\Domain\ProbeStatus;

final readonly class ReadProbeStatus
{
    public function __construct(private AvailabilityProbe $probe) {}

    public function handle(): ProbeStatus
    {
        return $this->probe->status();
    }
}
