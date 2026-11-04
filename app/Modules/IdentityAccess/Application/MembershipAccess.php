<?php

namespace App\Modules\IdentityAccess\Application;

use App\Modules\IdentityAccess\Application\Contracts\MembershipActor;
use App\Modules\IdentityAccess\Application\Contracts\MembershipTransaction;
use App\Modules\IdentityAccess\Application\Contracts\MembershipUsers;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\MembershipRepository;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantAuthorization;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantCapability;
use App\Modules\IdentityAccess\Application\Data\MembershipData;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Domain\Entities\OrganizationMembership;
use App\Modules\IdentityAccess\Domain\Entities\User;
use App\Modules\IdentityAccess\Domain\MembershipPermission;
use App\Modules\IdentityAccess\Domain\MembershipPolicy;
use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Domain\MembershipStatus;
use App\Modules\IdentityAccess\Domain\UserStatus;
use App\Modules\Organizations\Application\Contracts\PublicApi\OrganizationDirectory;
use App\Modules\Organizations\Application\Contracts\PublicApi\OrganizationSnapshot;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;
use LogicException;

final readonly class MembershipAccess
{
    public function __construct(
        public MembershipRepository $memberships, public UserLookup $users,
        public MembershipUsers $memberUsers, public OrganizationDirectory $organizations,
        public MembershipTransaction $transaction, private MembershipActor $actor,
        private MembershipPolicy $policy,
        private TenantAuthorization $tenantAuthorization,
    ) {}

    /** @param list<string> $extraUsers */
    public function forContext(TenantContext $context, array $extraUsers = []): OrganizationSnapshot
    {
        $organization = $this->organization($context->organizationPublicId);
        $this->lockOrganizationUsers($organization, [...$extraUsers, $context->actorPublicId]);
        $this->tenantAuthorization->authorize($context, TenantCapability::ManageMembers);

        return $organization;
    }

    public function actor(): User
    {
        $identity = $this->actor->identity();
        $this->transaction->lockUsers([$identity->publicId]);
        $user = $this->users->get($identity->publicId);
        if ($user->status !== UserStatus::Active || $user->version !== $identity->version) {
            throw new ApplicationFailure(FailureKind::Unauthenticated, 'memberships.unauthenticated', 'Authentication is required.');
        }

        return $user;
    }

    public function organization(string $publicId): OrganizationSnapshot
    {
        $organization = $this->organizations->findByPublicId($publicId, true);
        if ($organization === null) {
            throw new ApplicationFailure(FailureKind::NotFound, 'organizations.not_found', 'Organization not found.');
        }

        return $organization;
    }

    /** @param list<string> $extraUsers */
    public function lockOrganizationUsers(OrganizationSnapshot $organization, array $extraUsers = []): void
    {
        $ids = $extraUsers;
        foreach ($this->memberships->forOrganization($organization->id) as $member) {
            $ids[] = $this->requireUser($member->userId)->publicId;
        }
        $this->transaction->lockUsers(array_values(array_unique($ids)));
    }

    public function authorize(OrganizationSnapshot $organization, User $actor): OrganizationMembership
    {
        $member = $this->memberships->findActive($organization->id, $actor->id ?? 0);
        if (! $this->policy->allows($organization->id, $member, $actor->status === UserStatus::Active, $organization->active, MembershipPermission::ManageMembers)) {
            $this->forbidden();
        }

        return $member ?? throw new LogicException('Authorized membership missing.');
    }

    public function targetPermission(OrganizationSnapshot $organization, OrganizationMembership $actor, ?MembershipRole $current, MembershipRole $requested): void
    {
        if (! $this->policy->canManageTarget($organization->id, $actor, true, $organization->active, $current, $requested)) {
            $this->forbidden();
        }
    }

    public function target(OrganizationSnapshot $organization, string $publicId, int $version): OrganizationMembership
    {
        $member = $this->memberships->findInOrganization($organization->id, $publicId);
        if ($member === null || $member->organizationId !== $organization->id) {
            throw new ApplicationFailure(FailureKind::NotFound, 'memberships.not_found', 'Membership not found.');
        }
        if ($member->version !== $version) {
            throw new ApplicationFailure(FailureKind::Conflict, 'memberships.concurrent_update', 'The membership was changed by another operation.');
        }

        return $member;
    }

    public function protectOwner(OrganizationMembership $target): void
    {
        if ($target->role !== MembershipRole::Owner || $target->status !== MembershipStatus::Active) {
            return;
        }
        foreach ($this->memberships->forOrganization($target->organizationId) as $member) {
            if ($member->id !== $target->id && $member->role === MembershipRole::Owner && $member->isEffective($this->requireUser($member->userId)->status === UserStatus::Active, true)) {
                return;
            }
        }
        throw new ApplicationFailure(FailureKind::Conflict, 'memberships.last_owner', 'The last active Owner cannot be revoked or demoted.');
    }

    public function requireUser(int $id): User
    {
        return $this->memberUsers->findById($id) ?? throw new LogicException('Membership user missing.');
    }

    public function data(OrganizationSnapshot $organization, OrganizationMembership $member): MembershipData
    {
        return MembershipData::fromEntity($member, $organization->publicId, UserData::fromEntity($this->requireUser($member->userId)));
    }

    public function requireActive(User $user): void
    {
        if ($user->status !== UserStatus::Active) {
            throw new ApplicationFailure(FailureKind::Conflict, 'memberships.inactive_user', 'The user must be active.');
        }
    }

    private function forbidden(): never
    {
        throw new ApplicationFailure(FailureKind::Forbidden, 'memberships.forbidden', 'This operation is not permitted for this organization.');
    }
}
