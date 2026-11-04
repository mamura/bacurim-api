<?php

namespace App\Modules\IdentityAccess\Application\Contracts\Persistence;

use App\Modules\IdentityAccess\Domain\Entities\OrganizationMembership;

interface MembershipRepository
{
    public function create(OrganizationMembership $membership): OrganizationMembership;

    public function update(OrganizationMembership $membership): OrganizationMembership;

    public function findByPublicId(string $publicId): ?OrganizationMembership;

    public function findForUser(int $organizationId, int $userId): ?OrganizationMembership;

    public function findActive(int $organizationId, int $userId): ?OrganizationMembership;

    /** @return list<OrganizationMembership> */
    public function forOrganization(int $organizationId): array;

    /** @return list<OrganizationMembership> */
    public function forUser(int $userId): array;
}
