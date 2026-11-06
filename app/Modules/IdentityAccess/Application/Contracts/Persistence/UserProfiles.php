<?php

namespace App\Modules\IdentityAccess\Application\Contracts\Persistence;

use App\Modules\IdentityAccess\Domain\Entities\User;
use App\Modules\IdentityAccess\Domain\UserProfile;
use DateTimeImmutable;

interface UserProfiles
{
    /** Internal privileged persistence port; callers authorize and lock before writing. */
    public function replaceProfile(string $publicId, UserProfile $profile, int $expectedVersion, DateTimeImmutable $now): User;
}
