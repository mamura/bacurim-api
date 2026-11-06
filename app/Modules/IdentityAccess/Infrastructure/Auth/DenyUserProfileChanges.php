<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth;

use App\Modules\IdentityAccess\Application\Contracts\UserProfileChangeAuthorizer;
use App\Modules\IdentityAccess\Domain\UserProfile;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;

final class DenyUserProfileChanges implements UserProfileChangeAuthorizer
{
    public function assertMayChange(string $subjectPublicId, UserProfile $requested): void
    {
        throw new ApplicationFailure(FailureKind::Forbidden, 'identity.profile_forbidden', 'Platform profile changes are not available.');
    }
}
