<?php

namespace App\Modules\IdentityAccess\Application\Data;

use App\Modules\IdentityAccess\Domain\Entities\OrganizationMembership;

final readonly class MembershipData
{
    public function __construct(public string $publicId, public string $organizationPublicId, public string $userPublicId, public string $userName, public string $role, public string $status, public string $createdAt, public string $updatedAt, public ?string $revokedAt, public int $version) {}

    public static function fromEntity(OrganizationMembership $membership, string $organizationPublicId, UserData $user): self
    {
        return new self($membership->publicId, $organizationPublicId, $user->publicId, $user->name, $membership->role->value, $membership->status->value, $membership->createdAt->format('Y-m-d\TH:i:s.uP'), $membership->updatedAt->format('Y-m-d\TH:i:s.uP'), $membership->revokedAt?->format('Y-m-d\TH:i:s.uP'), $membership->version);
    }
}
