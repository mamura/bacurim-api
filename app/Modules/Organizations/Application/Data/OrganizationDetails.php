<?php

namespace App\Modules\Organizations\Application\Data;

final readonly class OrganizationDetails
{
    public function __construct(public string $name, public ?string $legalName = null, public ?string $document = null) {}
}
