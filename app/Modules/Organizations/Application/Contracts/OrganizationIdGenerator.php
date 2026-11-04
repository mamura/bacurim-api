<?php

namespace App\Modules\Organizations\Application\Contracts;

interface OrganizationIdGenerator
{
    public function generate(): string;
}
