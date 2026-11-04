<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use DateTimeImmutable;

interface IdentityClock
{
    public function now(): DateTimeImmutable;
}
