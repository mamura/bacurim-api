<?php

namespace App\Modules\IdentityAccess\Infrastructure\Services;

use App\Modules\IdentityAccess\Application\Contracts\IdentityClock;
use DateTimeImmutable;
use DateTimeZone;

final class SystemIdentityClock implements IdentityClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
