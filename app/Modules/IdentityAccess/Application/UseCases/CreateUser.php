<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Modules\IdentityAccess\Application\Contracts\IdentityClock;
use App\Modules\IdentityAccess\Application\Contracts\IdentityIdGenerator;
use App\Modules\IdentityAccess\Application\Contracts\PasswordHasher;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Application\Data\UserDetails;
use App\Modules\IdentityAccess\Domain\Entities\User;
use App\Modules\IdentityAccess\Domain\Exceptions\InvalidIdentity;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use SensitiveParameter;

final readonly class CreateUser
{
    public function __construct(private UserRepository $repository, private IdentityClock $clock, private IdentityIdGenerator $ids, private PasswordHasher $hasher) {}

    public function execute(UserDetails $input, #[SensitiveParameter] string $password): UserData
    {
        try {
            $user = User::create($this->ids->generate(), $input->name, new EmailAddress($input->email), $this->clock->now());
        } catch (InvalidIdentity $failure) {
            throw new ApplicationFailure(FailureKind::Validation, 'identity.invalid', $failure->getMessage());
        }

        return UserData::fromEntity($this->repository->create($user, $this->hasher->hash($password)));
    }
}
