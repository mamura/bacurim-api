<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

interface IdentityIdGenerator
{
    public function generate(): string;
}
