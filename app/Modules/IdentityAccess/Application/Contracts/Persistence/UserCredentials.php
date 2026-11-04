<?php

namespace App\Modules\IdentityAccess\Application\Contracts\Persistence;

use App\Modules\IdentityAccess\Application\Security\PasswordHash;
use App\Modules\IdentityAccess\Domain\Entities\User;
use DateTimeImmutable;
use SensitiveParameter;

interface UserCredentials
{
    public function getHash(string $publicId, int $expectedVersion): PasswordHash;

    public function replace(string $publicId, #[SensitiveParameter] PasswordHash $hash, int $expectedVersion, DateTimeImmutable $now): User;
}
