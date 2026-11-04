<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth;

use App\Modules\IdentityAccess\Application\Contracts\PasswordChangeAuthorizer;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Domain\UserStatus;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use Illuminate\Http\Request;

final readonly class RequestPasswordChangeAuthorizer implements PasswordChangeAuthorizer
{
    public function __construct(private Request $request) {}

    public function assertMayChange(string $subjectPublicId): void
    {
        $context = $this->request->attributes->get('bacurim.authenticated_identity');
        $actor = $this->request->user('web');
        if (! $context instanceof UserData || ! $actor instanceof UserRecord || $context->status !== UserStatus::Active
            || $context->publicId !== strtolower($subjectPublicId) || $actor->public_id !== $context->publicId) {
            throw new ApplicationFailure(FailureKind::Forbidden, 'identity.password_change_unavailable', 'An authenticated password change flow is required.');
        }
    }
}
