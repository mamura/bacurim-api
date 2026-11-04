<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Modules\IdentityAccess\Application\Contracts\SessionAuthentication;

final readonly class LogoutUser
{
    public function __construct(private SessionAuthentication $authentication) {}

    public function execute(): void
    {
        $this->authentication->logout();
    }
}
