<?php

namespace App\Modules\IdentityAccess\Infrastructure\Services;

use App\Modules\IdentityAccess\Application\Contracts\PasswordChangeAuthorizer;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;

final class DenyPasswordChanges implements PasswordChangeAuthorizer
{
    public function assertMayChange(string $subjectPublicId): void
    {
        throw new ApplicationFailure(FailureKind::Forbidden, 'identity.password_change_unavailable', 'An authenticated password change flow is required.');
    }
}
