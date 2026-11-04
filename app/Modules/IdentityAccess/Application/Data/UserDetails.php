<?php

namespace App\Modules\IdentityAccess\Application\Data;

final readonly class UserDetails
{
    public function __construct(public string $name, public string $email) {}
}
