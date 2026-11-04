<?php

namespace App\Modules\IdentityAccess\Domain\Entities;

use App\Modules\IdentityAccess\Domain\Exceptions\InvalidMembership;
use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Domain\MembershipStatus;
use DateTimeImmutable;

final readonly class OrganizationMembership
{
    public string $publicId;

    public function __construct(
        public ?int $id, string $publicId,
        public int $organizationId, public int $userId,
        public MembershipRole $role, public MembershipStatus $status,
        public DateTimeImmutable $createdAt, public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $revokedAt = null, public int $version = 0,
    ) {
        if (($id !== null && $id < 1) || $organizationId < 1 || $userId < 1 ||
            $version < 0 || (($id === null) !== ($version === 0))) {
            throw new InvalidMembership('Invalid membership persistence state.');
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $publicId) !== 1) {
            throw new InvalidMembership('The public identifier must be a UUID.');
        }
        if ($updatedAt < $createdAt || (($status === MembershipStatus::Revoked) !== ($revokedAt !== null)) ||
            ($revokedAt !== null && ($revokedAt < $createdAt || $revokedAt != $updatedAt))) {
            throw new InvalidMembership('Membership timestamps and status are inconsistent.');
        }
        $this->publicId = strtolower($publicId);
    }

    public static function create(string $publicId, int $organizationId, int $userId, MembershipRole $role, DateTimeImmutable $now, bool $userActive, bool $organizationActive): self
    {
        self::requireActiveReferences($userActive, $organizationActive);

        return new self(null, $publicId, $organizationId, $userId, $role, MembershipStatus::Active, $now, $now);
    }

    public function activate(DateTimeImmutable $now, bool $userActive, bool $organizationActive): self
    {
        self::requireActiveReferences($userActive, $organizationActive);
        $this->validateTime($now);
        if ($this->status !== MembershipStatus::Revoked) {
            throw new InvalidMembership('Only revoked memberships can be reactivated.');
        }

        return new self($this->id, $this->publicId, $this->organizationId, $this->userId, $this->role, MembershipStatus::Active, $this->createdAt, $now, null, $this->version);
    }

    public function revoke(DateTimeImmutable $now): self
    {
        $this->validateTime($now);
        if ($this->status !== MembershipStatus::Active) {
            throw new InvalidMembership('Only active memberships can be revoked.');
        }

        return new self($this->id, $this->publicId, $this->organizationId, $this->userId, $this->role, MembershipStatus::Revoked, $this->createdAt, $now, $now, $this->version);
    }

    public function changeRole(MembershipRole $role, DateTimeImmutable $now): self
    {
        $this->validateTime($now);
        if ($this->status !== MembershipStatus::Active) {
            throw new InvalidMembership('Revoked memberships cannot change roles.');
        }

        return new self($this->id, $this->publicId, $this->organizationId, $this->userId, $role, $this->status, $this->createdAt, $now, null, $this->version);
    }

    public function isEffective(bool $userActive, bool $organizationActive): bool
    {
        return $this->status === MembershipStatus::Active && $userActive && $organizationActive;
    }

    private static function requireActiveReferences(bool $userActive, bool $organizationActive): void
    {
        if (! $userActive || ! $organizationActive) {
            throw new InvalidMembership('Membership activation requires an active user and organization.');
        }
    }

    private function validateTime(DateTimeImmutable $now): void
    {
        if ($now < $this->updatedAt) {
            throw new InvalidMembership('Membership update time cannot move backwards.');
        }
    }
}
