<?php

namespace App\Modules\IdentityAccess\Application\Security;

use App\Modules\IdentityAccess\Application\Contracts\MembershipTransaction;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\MembershipRepository;
use App\Modules\IdentityAccess\Application\UserLookup;
use App\Modules\IdentityAccess\Domain\MembershipStatus;
use App\Modules\IdentityAccess\Domain\UserProfile;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;

final readonly class MembershipCardinality
{
    public function __construct(private MembershipTransaction $transaction, private UserLookup $users, private MembershipRepository $memberships) {}

    /** Caller must own the organization lock first, if operating on an existing organization. */
    public function assertMayActivate(string $userPublicId, ?string $existingMembershipPublicId = null): void
    {
        $this->transaction->lockUsers([$userPublicId]);
        $user = $this->users->get($userPublicId);
        if ($user->profile === UserProfile::Consultant) {
            return;
        }
        foreach ($this->memberships->forUser($user->id ?? 0) as $membership) {
            if ($membership->status === MembershipStatus::Active && $membership->publicId !== $existingMembershipPublicId) {
                throw new ApplicationFailure(FailureKind::Conflict, 'memberships.active_limit', 'Standard users may have at most one active organization membership.');
            }
        }
    }
}
