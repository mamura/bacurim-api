<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth;

use App\Modules\IdentityAccess\Application\Contracts\MembershipActor;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use Illuminate\Http\Request;

final readonly class RequestMembershipActor implements MembershipActor
{
    public function __construct(private Request $request) {}

    public function identity(): UserData
    {
        $identity = $this->request->attributes->get('bacurim.authenticated_identity');
        $user = $this->request->user('web');
        if (! $identity instanceof UserData || ! $user instanceof UserRecord || $identity->publicId !== $user->public_id) {
            throw new ApplicationFailure(FailureKind::Unauthenticated, 'memberships.unauthenticated', 'Authentication is required.');
        }

        return $identity;
    }
}
