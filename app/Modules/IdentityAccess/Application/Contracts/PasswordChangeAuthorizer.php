<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

interface PasswordChangeAuthorizer
{
    /** Authorize the validated current actor for this subject, or throw. */
    public function assertMayChange(string $subjectPublicId): void;
}
