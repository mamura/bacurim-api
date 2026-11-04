<?php

namespace App\Modules\Organizations\Infrastructure\Services;

use App\Modules\Organizations\Application\Contracts\OrganizationClock;
use DateTimeImmutable;
use DateTimeZone;

final class SystemOrganizationClock implements OrganizationClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
