<?php

namespace App\Modules\IdentityAccess\Application\Security;

use App\Modules\IdentityAccess\Application\Contracts\Persistence\MembershipRepository;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantAuthorization;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantCapability;
use App\Modules\IdentityAccess\Application\UserLookup;
use App\Modules\IdentityAccess\Domain\MembershipPermission;
use App\Modules\IdentityAccess\Domain\MembershipPolicy;
use App\Modules\IdentityAccess\Domain\UserStatus;
use App\Modules\Organizations\Application\Contracts\PublicApi\OrganizationDirectory;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;

final readonly class OrganizationTenantAuthorization implements TenantAuthorization
{
    public function __construct(private UserLookup $users, private OrganizationDirectory $organizations, private MembershipRepository $memberships, private MembershipPolicy $policy) {}

    public function authorize(TenantContext $context, TenantCapability $capability): void
    {
        $org = $this->organizations->findByPublicId($context->organizationPublicId);
        $user = $this->users->get($context->actorPublicId);
        $member = $org === null ? null : $this->memberships->findActive($org->id, $user->id ?? 0);
        if ($org === null || $org->id !== $context->organizationId || $user->version !== $context->actorVersion ||
            $member === null || $member->publicId !== $context->membershipPublicId || $member->version !== $context->membershipVersion ||
            ! $this->policy->allows($org->id, $member, $user->status === UserStatus::Active, $org->active, MembershipPermission::from($capability->value))) {
            throw new ApplicationFailure(FailureKind::Forbidden, 'tenant.forbidden', 'The organization context or permission is no longer authorized.');
        }
    }
}
