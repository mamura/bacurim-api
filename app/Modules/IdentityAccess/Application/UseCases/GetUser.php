<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Application\UserLookup;

final readonly class GetUser
{
    public function __construct(private UserLookup $lookup) {}

    public function execute(string $publicId): UserData
    {
        return UserData::fromEntity($this->lookup->get($publicId));
    }
}
