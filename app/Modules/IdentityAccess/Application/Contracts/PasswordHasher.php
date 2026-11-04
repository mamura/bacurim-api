<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Security\PasswordHash;
use SensitiveParameter;

interface PasswordHasher
{
    public function hash(#[SensitiveParameter] string $password): PasswordHash;

    public function verify(#[SensitiveParameter] string $password, PasswordHash $hash): bool;

    public function needsRehash(PasswordHash $hash): bool;
}
