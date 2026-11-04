<?php

namespace App\Modules\Organizations\Application\UseCases;

use App\Modules\Organizations\Application\Contracts\OrganizationClock;
use App\Modules\Organizations\Application\Contracts\Persistence\OrganizationRepository;
use App\Modules\Organizations\Application\Data\OrganizationData;
use App\Modules\Organizations\Application\OrganizationLookup;
use App\Modules\Organizations\Domain\Exceptions\InvalidOrganization;
use App\Modules\Organizations\Domain\OrganizationStatus;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;

final readonly class ChangeOrganizationStatus
{
    public function __construct(private OrganizationRepository $repository, private OrganizationLookup $lookup, private OrganizationClock $clock) {}

    public function execute(string $publicId, OrganizationStatus $status, int $expectedVersion): OrganizationData
    {
        $entity = $this->lookup->get($publicId, $expectedVersion);
        try {
            $updated = $entity->changeStatus($status, $this->clock->now());
        } catch (InvalidOrganization $failure) {
            throw new ApplicationFailure(FailureKind::Validation, 'organizations.invalid', $failure->getMessage());
        }

        return OrganizationData::fromEntity($this->repository->update($updated));
    }
}
