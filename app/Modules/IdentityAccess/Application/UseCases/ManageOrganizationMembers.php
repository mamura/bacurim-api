<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Modules\IdentityAccess\Application\Contracts\IdentityClock;
use App\Modules\IdentityAccess\Application\Contracts\IdentityIdGenerator;
use App\Modules\IdentityAccess\Application\Data\MembershipData;
use App\Modules\IdentityAccess\Application\MembershipAccess;
use App\Modules\IdentityAccess\Domain\Entities\OrganizationMembership;
use App\Modules\IdentityAccess\Domain\Exceptions\InvalidMembership;
use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\Organizations\Application\Contracts\PublicApi\OrganizationSnapshot;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;

final readonly class ManageOrganizationMembers
{
    public function __construct(private MembershipAccess $access, private IdentityClock $clock, private IdentityIdGenerator $ids) {}

    /** @return list<MembershipData> */
    public function list(string $organizationPublicId): array
    {
        return $this->access->transaction->run(function () use ($organizationPublicId): array {
            $org = $this->access->organization($organizationPublicId);
            $this->access->lockOrganizationUsers($org);
            $this->access->authorize($org, $this->access->actor());

            return array_map(fn (OrganizationMembership $member): MembershipData => $this->access->data($org, $member), $this->access->memberships->forOrganization($org->id));
        });
    }

    public function add(string $organizationPublicId, string $userPublicId, MembershipRole $role): MembershipData
    {
        return $this->access->transaction->run(function () use ($organizationPublicId, $userPublicId, $role): MembershipData {
            $org = $this->access->organization($organizationPublicId);
            $this->access->lockOrganizationUsers($org, [$userPublicId]);
            $actor = $this->access->authorize($org, $this->access->actor());
            $this->access->targetPermission($org, $actor, null, $role);
            $user = $this->access->users->get($userPublicId);
            $this->access->requireActive($user);
            if ($this->access->memberships->findForUser($org->id, $user->id ?? 0) !== null) {
                throw new ApplicationFailure(FailureKind::Conflict, 'memberships.duplicate', 'The user already has a membership; reactivate the existing membership if revoked.');
            }
            $member = OrganizationMembership::create($this->ids->generate(), $org->id, $user->id ?? 0, $role, $this->clock->now(), true, $org->active);

            return $this->access->data($org, $this->access->memberships->create($member));
        });
    }

    public function changeRole(string $organizationPublicId, string $membershipPublicId, MembershipRole $role, int $expectedVersion): MembershipData
    {
        return $this->mutate($organizationPublicId, $membershipPublicId, $expectedVersion, function (OrganizationSnapshot $org, OrganizationMembership $actor, OrganizationMembership $target) use ($role): OrganizationMembership {
            $this->access->targetPermission($org, $actor, $target->role, $role);
            if ($role !== MembershipRole::Owner) {
                $this->access->protectOwner($target);
            }

            return $target->changeRole($role, $this->clock->now());
        });
    }

    public function activate(string $organizationPublicId, string $membershipPublicId, int $expectedVersion): MembershipData
    {
        return $this->mutate($organizationPublicId, $membershipPublicId, $expectedVersion, function (OrganizationSnapshot $org, OrganizationMembership $actor, OrganizationMembership $target): OrganizationMembership {
            $this->access->targetPermission($org, $actor, $target->role, $target->role);
            $this->access->requireActive($this->access->requireUser($target->userId));

            return $target->activate($this->clock->now(), true, $org->active);
        });
    }

    public function revoke(string $organizationPublicId, string $membershipPublicId, int $expectedVersion): MembershipData
    {
        return $this->mutate($organizationPublicId, $membershipPublicId, $expectedVersion, function (OrganizationSnapshot $org, OrganizationMembership $actor, OrganizationMembership $target): OrganizationMembership {
            $this->access->targetPermission($org, $actor, $target->role, $target->role);
            $this->access->protectOwner($target);

            return $target->revoke($this->clock->now());
        });
    }

    /** @param callable(OrganizationSnapshot,OrganizationMembership,OrganizationMembership):OrganizationMembership $change */
    private function mutate(string $organizationPublicId, string $membershipPublicId, int $expectedVersion, callable $change): MembershipData
    {
        return $this->access->transaction->run(function () use ($organizationPublicId, $membershipPublicId, $expectedVersion, $change): MembershipData {
            $org = $this->access->organization($organizationPublicId);
            $this->access->lockOrganizationUsers($org);
            $actor = $this->access->authorize($org, $this->access->actor());
            $target = $this->access->target($org, $membershipPublicId, $expectedVersion);
            try {
                $changed = $change($org, $actor, $target);
            } catch (InvalidMembership $failure) {
                throw new ApplicationFailure(FailureKind::Conflict, 'memberships.invalid_transition', $failure->getMessage());
            }

            return $this->access->data($org, $this->access->memberships->update($changed));
        });
    }
}
