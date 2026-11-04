<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Modules\IdentityAccess\Application\Contracts\SessionAuthentication;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;

final readonly class GetAuthenticatedUser
{
    public function __construct(private SessionAuthentication $authentication) {}

    public function execute(): UserData
    {
        return $this->authentication->current() ?? throw new ApplicationFailure(FailureKind::Unauthenticated, 'identity.unauthenticated', 'Authentication is required.');
    }
}
