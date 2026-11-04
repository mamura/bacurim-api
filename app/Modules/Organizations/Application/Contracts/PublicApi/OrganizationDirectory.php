<?php

namespace App\Modules\Organizations\Application\Contracts\PublicApi;

interface OrganizationDirectory
{
    public function findByPublicId(string $publicId, bool $lock = false): ?OrganizationSnapshot;

    public function findById(int $id): ?OrganizationSnapshot;

    public function createForRegistration(string $name, ?string $legalName, ?string $document): OrganizationSnapshot;
}
