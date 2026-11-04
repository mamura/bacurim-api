<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Resources;

use App\Modules\IdentityAccess\Application\Data\MembershipData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MembershipResource extends JsonResource
{
    public function __construct(private readonly MembershipData $membership)
    {
        parent::__construct($membership);
    }

    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['public_id' => $this->membership->publicId, 'organization_public_id' => $this->membership->organizationPublicId,
            'user' => ['public_id' => $this->membership->userPublicId, 'name' => $this->membership->userName],
            'role' => $this->membership->role, 'status' => $this->membership->status, 'version' => $this->membership->version,
            'created_at' => $this->membership->createdAt, 'updated_at' => $this->membership->updatedAt, 'revoked_at' => $this->membership->revokedAt];
    }
}
