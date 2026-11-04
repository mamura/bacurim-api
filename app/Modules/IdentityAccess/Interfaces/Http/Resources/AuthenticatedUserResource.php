<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Resources;

use App\Modules\IdentityAccess\Application\Data\UserData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AuthenticatedUserResource extends JsonResource
{
    public function __construct(private readonly UserData $user)
    {
        parent::__construct($user);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['public_id' => $this->user->publicId, 'name' => $this->user->name, 'email' => $this->user->email,
            'status' => $this->user->status->value, 'email_verified_at' => $this->user->emailVerifiedAt?->format(DATE_ATOM)];
    }
}
