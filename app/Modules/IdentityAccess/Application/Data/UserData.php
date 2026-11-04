<?php

namespace App\Modules\IdentityAccess\Application\Data;

use App\Modules\IdentityAccess\Domain\Entities\User;
use App\Modules\IdentityAccess\Domain\UserStatus;
use DateTimeImmutable;

final readonly class UserData
{
    public function __construct(public string $publicId, public string $name, public string $email, public UserStatus $status, public ?DateTimeImmutable $emailVerifiedAt, public DateTimeImmutable $createdAt, public DateTimeImmutable $updatedAt, public int $version) {}

    public static function fromEntity(User $user): self
    {
        return new self($user->publicId, $user->name, $user->email->value, $user->status, $user->emailVerifiedAt, $user->createdAt, $user->updatedAt, $user->version);
    }
}
