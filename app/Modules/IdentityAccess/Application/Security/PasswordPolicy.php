<?php

namespace App\Modules\IdentityAccess\Application\Security;

use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use SensitiveParameter;

final class PasswordPolicy
{
    public function validate(#[SensitiveParameter] string $password): void
    {
        if (! mb_check_encoding($password, 'UTF-8') || mb_strlen($password) < 15 || mb_strlen($password) > 128
            || trim($password) === '' || preg_match('/[\x00-\x1F\x7F]/u', $password)
            || in_array(strtolower($password), ['passwordpassword', '123456789012345', 'qwertyqwertyqwerty', 'letmeinletmeinletmein'], true)) {
            throw new ApplicationFailure(FailureKind::Validation, 'identity.password_policy', 'Use a password of 15 to 128 characters without control characters or commonly guessed values.');
        }
    }
}
