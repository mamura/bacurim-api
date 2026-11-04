<?php

namespace App\Modules\IdentityAccess\Application\Data;

use App\Modules\IdentityAccess\Domain\Entities\OrganizationMembership;
use DateTimeImmutable;
use DateTimeZone;

final readonly class MembershipData
{
    public function __construct(public string $publicId, public string $organizationPublicId, public string $userPublicId, public string $userName, public string $role, public string $status, public string $createdAt, public string $updatedAt, public ?string $revokedAt, public int $version) {}

    public static function fromEntity(OrganizationMembership $membership, string $organizationPublicId, UserData $user): self
    {
        return new self($membership->publicId, $organizationPublicId, $user->publicId, $user->name, $membership->role->value, $membership->status->value, self::instant($membership->createdAt), self::instant($membership->updatedAt), $membership->revokedAt === null ? null : self::instant($membership->revokedAt), $membership->version);
    }

    private static function instant(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }
}
