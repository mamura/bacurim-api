<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use SensitiveParameter;

interface SessionAuthentication
{
    public function login(EmailAddress $email, #[SensitiveParameter] string $password): UserData;

    public function logout(): void;

    public function current(): ?UserData;
}
