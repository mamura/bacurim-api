<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Modules\IdentityAccess\Application\Contracts\Persistence\MembershipRepository;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Application\UserLookup;
use App\Modules\IdentityAccess\Domain\MembershipPermission;
use App\Modules\IdentityAccess\Domain\MembershipPolicy;
use App\Modules\IdentityAccess\Domain\UserStatus;
use App\Modules\Organizations\Application\Contracts\PublicApi\OrganizationDirectory;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;

final readonly class ResolveTenantContext
{
    public function __construct(private UserLookup $users, private OrganizationDirectory $organizations, private MembershipRepository $memberships, private MembershipPolicy $policy) {}

    public function execute(string $organizationPublicId, UserData $identity): TenantContext
    {
        $user = $this->users->get($identity->publicId);
        if ($user->status !== UserStatus::Active || $user->version !== $identity->version) {
            throw new ApplicationFailure(FailureKind::Unauthenticated, 'tenant.unauthenticated', 'Authentication is required.');
        }
        $org = $this->organizations->findByPublicId($organizationPublicId);
        if ($org === null) {
            throw new ApplicationFailure(FailureKind::NotFound, 'tenant.not_found', 'Organization not found.');
        }
        $member = $this->memberships->findActive($org->id, $user->id ?? 0);
        if (! $this->policy->allows($org->id, $member, true, $org->active, MembershipPermission::ReadFinancial) || $member === null) {
            throw new ApplicationFailure(FailureKind::Forbidden, 'tenant.forbidden', 'Access to this organization is not permitted.');
        }

        return new TenantContext($org->id, $org->publicId, $user->publicId, $user->version, $member->publicId, $member->version);
    }
}
