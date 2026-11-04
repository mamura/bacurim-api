<?php

namespace App\Modules\IdentityAccess\Infrastructure\Persistence;

use RuntimeException;

final class IdentityPersistenceFailure extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Identity persistence failed.');
    }
}
