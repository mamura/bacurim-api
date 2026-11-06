<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Domain\UserProfile;

interface UserProfileChangeAuthorizer
{
    public function assertMayChange(string $subjectPublicId, UserProfile $requested): void;
}
