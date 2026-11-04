<?php

namespace App\Modules\IdentityAccess\Application\Contracts\Persistence;

use App\Modules\IdentityAccess\Application\Security\PasswordHash;
use App\Modules\IdentityAccess\Domain\Entities\User;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use SensitiveParameter;

interface UserRepository
{
    public function create(User $user, #[SensitiveParameter] PasswordHash $password): User;

    public function findByPublicId(string $publicId): ?User;

    public function findByEmail(EmailAddress $email): ?User;

    public function update(User $user): User;
}
