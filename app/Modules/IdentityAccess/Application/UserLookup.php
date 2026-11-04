<?php

namespace App\Modules\IdentityAccess\Application;

use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Domain\Entities\User;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;

final readonly class UserLookup
{
    public function __construct(private UserRepository $repository) {}

    public function get(string $publicId, ?int $expectedVersion = null): User
    {
        $user = $this->repository->findByPublicId($publicId);
        if ($user === null) {
            throw new ApplicationFailure(FailureKind::NotFound, 'identity.not_found', 'User not found.');
        }
        if ($expectedVersion !== null && $user->version !== $expectedVersion) {
            throw new ApplicationFailure(FailureKind::Conflict, 'identity.concurrent_update', 'The user was changed by another operation.');
        }

        return $user;
    }
}
