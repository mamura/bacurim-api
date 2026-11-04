<?php

namespace App\Modules\Organizations\Application\Contracts\Persistence;

use App\Modules\Organizations\Domain\Entities\Organization;

interface OrganizationRepository
{
    public function create(Organization $organization): Organization;

    public function findByPublicId(string $publicId): ?Organization;

    public function update(Organization $organization): Organization;
}
