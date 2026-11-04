<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

final class ValidateSpaCsrf extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
