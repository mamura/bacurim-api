<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Modules\IdentityAccess\Application\Contracts\IdentityClock;
use App\Modules\IdentityAccess\Application\Contracts\IdentityIdGenerator;
use App\Modules\IdentityAccess\Application\Data\MembershipData;
use App\Modules\IdentityAccess\Application\MembershipAccess;
use App\Modules\IdentityAccess\Domain\Entities\OrganizationMembership;
use App\Modules\IdentityAccess\Domain\MembershipRole;

final readonly class RegisterOrganizationWithOwner
{
    public function __construct(private MembershipAccess $access, private IdentityClock $clock, private IdentityIdGenerator $ids) {}

    public function execute(string $name, ?string $legalName = null, ?string $document = null): MembershipData
    {
        return $this->access->transaction->run(function () use ($name, $legalName, $document): MembershipData {
            $actor = $this->access->actor();
            $organization = $this->access->organizations->createForRegistration($name, $legalName, $document);
            $owner = OrganizationMembership::create($this->ids->generate(), $organization->id, $actor->id ?? 0, MembershipRole::Owner, $this->clock->now(), true, $organization->active);

            return $this->access->data($organization, $this->access->memberships->create($owner));
        });
    }
}
