<?php

namespace App\Modules\Organizations\Infrastructure\Services;

use App\Modules\Organizations\Application\Contracts\OrganizationIdGenerator;
use Illuminate\Support\Str;

final class UuidOrganizationIdGenerator implements OrganizationIdGenerator
{
    public function generate(): string
    {
        return (string) Str::uuid();
    }
}
