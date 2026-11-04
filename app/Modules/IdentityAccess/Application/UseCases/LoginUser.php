<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Modules\IdentityAccess\Application\Contracts\SessionAuthentication;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Domain\Exceptions\InvalidIdentity;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use SensitiveParameter;

final readonly class LoginUser
{
    public function __construct(private SessionAuthentication $authentication) {}

    public function execute(string $email, #[SensitiveParameter] string $password): UserData
    {
        try {
            $address = new EmailAddress($email);
        } catch (InvalidIdentity $failure) {
            throw new ApplicationFailure(FailureKind::Validation, 'identity.invalid', $failure->getMessage());
        }

        return $this->authentication->login($address, $password);
    }
}
