<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Modules\IdentityAccess\Application\Contracts\IdentityClock;
use App\Modules\IdentityAccess\Application\Contracts\MembershipTransaction;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserProfiles;
use App\Modules\IdentityAccess\Application\Contracts\UserProfileChangeAuthorizer;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Application\Security\MembershipCardinality;
use App\Modules\IdentityAccess\Application\UserLookup;
use App\Modules\IdentityAccess\Domain\UserProfile;

final readonly class ChangeUserProfile
{
    public function __construct(
        private UserProfileChangeAuthorizer $authorizer, private MembershipTransaction $transaction,
        private UserLookup $users, private UserProfiles $profiles,
        private MembershipCardinality $cardinality, private IdentityClock $clock,
    ) {}

    public function execute(string $publicId, UserProfile $profile, int $expectedVersion): UserData
    {
        $this->authorizer->assertMayChange($publicId, $profile);

        return $this->transaction->run(function () use ($publicId, $profile, $expectedVersion): UserData {
            // Global operation: lock identity only, never acquire an existing organization afterwards.
            $this->transaction->lockUsers([$publicId]);
            $user = $this->users->get($publicId, $expectedVersion);
            if ($profile === UserProfile::Standard) {
                $this->cardinality->assertMayBecomeStandard($publicId);
            }

            return UserData::fromEntity($this->profiles->replaceProfile($user->publicId, $profile, $expectedVersion, $this->clock->now()));
        });
    }
}
