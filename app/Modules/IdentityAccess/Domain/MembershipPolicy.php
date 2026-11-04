<?php

namespace App\Modules\IdentityAccess\Domain;

use App\Modules\IdentityAccess\Domain\Entities\OrganizationMembership;

final class MembershipPolicy
{
    public function allows(int $organizationId, ?OrganizationMembership $actor, bool $userActive, bool $organizationActive, MembershipPermission $permission): bool
    {
        if ($actor === null || $actor->organizationId !== $organizationId || ! $actor->isEffective($userActive, $organizationActive)) {
            return false;
        }

        return match ($permission) {
            MembershipPermission::ReadFinancial => true,
            MembershipPermission::WriteFinancial => $actor->role !== MembershipRole::Viewer,
            MembershipPermission::ReadMembers,MembershipPermission::ManageMembers,MembershipPermission::ManageSettings => in_array($actor->role, [MembershipRole::Owner, MembershipRole::Admin], true),
            MembershipPermission::ManageOwnership => $actor->role === MembershipRole::Owner,
        };
    }

    public function canManageTarget(int $organizationId, ?OrganizationMembership $actor, bool $userActive, bool $organizationActive, ?MembershipRole $currentRole, MembershipRole $requestedRole): bool
    {
        if (! $this->allows($organizationId, $actor, $userActive, $organizationActive, MembershipPermission::ManageMembers)) {
            return false;
        }

        return $actor?->role === MembershipRole::Owner || ($currentRole !== MembershipRole::Owner && $requestedRole !== MembershipRole::Owner);
    }
}
