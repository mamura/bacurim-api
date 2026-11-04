<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Modules\IdentityAccess\Application\Contracts\IdentityClock;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Application\Data\UserDetails;
use App\Modules\IdentityAccess\Application\UserLookup;
use App\Modules\IdentityAccess\Domain\Exceptions\InvalidIdentity;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;

final readonly class UpdateUser
{
    public function __construct(private UserRepository $repository, private UserLookup $lookup, private IdentityClock $clock) {}

    public function execute(string $publicId, UserDetails $input, int $expectedVersion): UserData
    {
        $user = $this->lookup->get($publicId, $expectedVersion);
        try {
            $updated = $user->updateDetails($input->name, new EmailAddress($input->email), $this->clock->now());
        } catch (InvalidIdentity $failure) {
            throw new ApplicationFailure(FailureKind::Validation, 'identity.invalid', $failure->getMessage());
        }

        return UserData::fromEntity($this->repository->update($updated));
    }
}
