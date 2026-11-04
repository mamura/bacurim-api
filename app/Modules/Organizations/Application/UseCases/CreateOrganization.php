<?php

namespace App\Modules\Organizations\Application\UseCases;

use App\Modules\Organizations\Application\Contracts\OrganizationClock;
use App\Modules\Organizations\Application\Contracts\OrganizationIdGenerator;
use App\Modules\Organizations\Application\Contracts\Persistence\OrganizationRepository;
use App\Modules\Organizations\Application\Data\OrganizationData;
use App\Modules\Organizations\Application\Data\OrganizationDetails;
use App\Modules\Organizations\Domain\Entities\Organization;
use App\Modules\Organizations\Domain\Exceptions\InvalidOrganization;
use App\Modules\Organizations\Domain\ValueObjects\OrganizationDocument;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;

final readonly class CreateOrganization
{
    public function __construct(private OrganizationRepository $repository, private OrganizationClock $clock, private OrganizationIdGenerator $ids) {}

    public function execute(OrganizationDetails $input): OrganizationData
    {
        try {
            $entity = Organization::create($this->ids->generate(), $input->name, $input->legalName, $input->document === null ? null : new OrganizationDocument($input->document), $this->clock->now());
        } catch (InvalidOrganization $failure) {
            throw new ApplicationFailure(FailureKind::Validation, 'organizations.invalid', $failure->getMessage());
        }

        return OrganizationData::fromEntity($this->repository->create($entity));
    }
}
