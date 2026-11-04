<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Domain\Entities\User;

interface MembershipUsers
{
    public function findById(int $id): ?User;
}
