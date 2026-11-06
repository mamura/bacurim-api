<?php

namespace Tests\Support;

use App\Modules\IdentityAccess\Application\Contracts\UserProfileChangeAuthorizer;
use App\Modules\IdentityAccess\Domain\UserProfile;

trait AllowsProfileChanges
{
    /** Explicit test-only authorization; production binding remains deny-all. */
    protected function allowProfileChanges(): void
    {
        app()->instance(UserProfileChangeAuthorizer::class, new class implements UserProfileChangeAuthorizer
        {
            public function assertMayChange(string $subjectPublicId, UserProfile $requested): void {}
        });
    }
}
