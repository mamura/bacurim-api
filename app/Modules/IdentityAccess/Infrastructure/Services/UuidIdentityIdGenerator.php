<?php

namespace App\Modules\IdentityAccess\Infrastructure\Services;

use App\Modules\IdentityAccess\Application\Contracts\IdentityIdGenerator;
use Illuminate\Support\Str;

final class UuidIdentityIdGenerator implements IdentityIdGenerator
{
    public function generate(): string
    {
        return (string) Str::uuid();
    }
}
