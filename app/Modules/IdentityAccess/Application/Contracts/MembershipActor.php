<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\UserData;

interface MembershipActor
{
    public function identity(): UserData;
}
