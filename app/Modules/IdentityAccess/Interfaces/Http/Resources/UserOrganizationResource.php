<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserOrganizationResource extends JsonResource
{
    /** @param array{public_id:string,name:string,role:string,membership_public_id:string} $organization */
    public function __construct(private readonly array $organization)
    {
        parent::__construct($organization);
    }

    /** @return array<string,string> */
    public function toArray(Request $request): array
    {
        return ['public_id' => $this->organization['public_id'], 'name' => $this->organization['name'], 'role' => $this->organization['role'], 'membership_public_id' => $this->organization['membership_public_id']];
    }
}
