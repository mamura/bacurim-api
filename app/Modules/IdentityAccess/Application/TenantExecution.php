<?php

namespace App\Modules\IdentityAccess\Application;

use App\Modules\IdentityAccess\Application\Contracts\MembershipTransaction;
use App\Modules\IdentityAccess\Application\Contracts\MembershipUsers;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\MembershipRepository;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantAuthorization;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantCapability;
use App\Modules\Organizations\Application\Contracts\PublicApi\OrganizationDirectory;
use App\Modules\Organizations\Application\Contracts\PublicApi\OrganizationSnapshot;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;
use LogicException;

/** Locks and validation shared by explicit HTTP use cases and queue execution. */
final readonly class TenantExecution
{
    public function __construct(public MembershipTransaction $transaction, public UserLookup $users, private MembershipUsers $memberUsers, private MembershipRepository $memberships, private OrganizationDirectory $organizations, private TenantAuthorization $authorization) {}

    /** @param list<string> $extraUsers */
    public function forContext(TenantContext $context, TenantCapability $required, array $extraUsers = []): OrganizationSnapshot
    {
        $organization = $this->organization($context->organizationPublicId);
        $this->lockOrganizationUsers($organization, [...$extraUsers, $context->actorPublicId]);
        $this->authorization->authorize($context, $required);

        return $organization;
    }

    public function organization(string $publicId): OrganizationSnapshot
    {
        return $this->organizations->findByPublicId($publicId, true) ?? throw new ApplicationFailure(FailureKind::NotFound, 'organizations.not_found', 'Organization not found.');
    }

    /** @param list<string> $extraUsers */
    public function lockOrganizationUsers(OrganizationSnapshot $organization, array $extraUsers): void
    {
        $ids = $extraUsers;
        foreach ($this->memberships->forOrganization($organization->id) as $member) {
            $user = $this->memberUsers->findById($member->userId) ?? throw new LogicException('Membership user missing.');
            $ids[] = $user->publicId;
        }
        $this->transaction->lockUsers(array_values(array_unique($ids)));
    }
}
