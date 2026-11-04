<?php

namespace App\Modules\Organizations\Application\UseCases;

use App\Modules\Organizations\Application\Data\OrganizationData;
use App\Modules\Organizations\Application\OrganizationLookup;

final readonly class GetOrganization
{
    public function __construct(private OrganizationLookup $lookup) {}

    public function execute(string $publicId): OrganizationData
    {
        return OrganizationData::fromEntity($this->lookup->get($publicId));
    }
}
