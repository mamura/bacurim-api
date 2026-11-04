<?php

namespace App\Modules\Organizations\Application\Contracts;

use DateTimeImmutable;

interface OrganizationClock
{
    public function now(): DateTimeImmutable;
}
