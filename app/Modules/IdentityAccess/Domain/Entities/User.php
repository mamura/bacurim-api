<?php

namespace App\Modules\IdentityAccess\Domain\Entities;

use App\Modules\IdentityAccess\Domain\Exceptions\InvalidIdentity;
use App\Modules\IdentityAccess\Domain\UserProfile;
use App\Modules\IdentityAccess\Domain\UserStatus;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use DateTimeImmutable;

final readonly class User
{
    public string $publicId;

    public string $name;

    public function __construct(
        public ?int $id, string $publicId, string $name,
        public EmailAddress $email, public UserStatus $status,
        public ?DateTimeImmutable $emailVerifiedAt,
        public DateTimeImmutable $createdAt, public DateTimeImmutable $updatedAt,
        public int $version = 0,
        public UserProfile $profile = UserProfile::Standard,
    ) {
        if (($id !== null && $id < 1) || $version < 0 || (($id === null) !== ($version === 0))) {
            throw new InvalidIdentity('Invalid identity persistence state.');
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $publicId) !== 1) {
            throw new InvalidIdentity('The public identifier must be a UUID.');
        }
        $name = trim($name);
        if (! mb_check_encoding($name, 'UTF-8') || mb_strlen($name) < 1 || mb_strlen($name) > 255 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
            throw new InvalidIdentity('User names must contain 1 to 255 characters without controls.');
        }
        if ($updatedAt < $createdAt || ($emailVerifiedAt !== null && ($emailVerifiedAt < $createdAt || $emailVerifiedAt > $updatedAt))) {
            throw new InvalidIdentity('Identity timestamps are inconsistent.');
        }
        $this->publicId = strtolower($publicId);
        $this->name = $name;
    }

    public static function create(string $publicId, string $name, EmailAddress $email, DateTimeImmutable $now): self
    {
        return new self(null, $publicId, $name, $email, UserStatus::Active, null, $now, $now);
    }

    public function updateDetails(string $name, EmailAddress $email, DateTimeImmutable $now): self
    {
        $this->validateTime($now);

        return new self($this->id, $this->publicId, $name, $email, $this->status, $email->value === $this->email->value ? $this->emailVerifiedAt : null, $this->createdAt, $now, $this->version, $this->profile);
    }

    public function changeStatus(UserStatus $status, DateTimeImmutable $now): self
    {
        $this->validateTime($now);

        return new self($this->id, $this->publicId, $this->name, $this->email, $status, $this->emailVerifiedAt, $this->createdAt, $now, $this->version, $this->profile);
    }

    private function validateTime(DateTimeImmutable $now): void
    {
        if ($now < $this->updatedAt) {
            throw new InvalidIdentity('Identity update time cannot move backwards.');
        }
    }
}
