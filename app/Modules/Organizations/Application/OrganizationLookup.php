<?php

namespace App\Modules\Organizations\Application;

use App\Modules\Organizations\Application\Contracts\Persistence\OrganizationRepository;
use App\Modules\Organizations\Domain\Entities\Organization;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;

final readonly class OrganizationLookup
{
    public function __construct(private OrganizationRepository $repository) {}

    public function get(string $publicId, ?int $expectedVersion = null): Organization
    {
        $entity = $this->repository->findByPublicId($publicId);
        if ($entity === null) {
            throw new ApplicationFailure(FailureKind::NotFound, 'organizations.not_found', 'Organization not found.');
        }
        if ($expectedVersion !== null && $entity->version !== $expectedVersion) {
            throw new ApplicationFailure(FailureKind::Conflict, 'organizations.concurrent_update', 'The organization was changed by another operation.');
        }

        return $entity;
    }
}
