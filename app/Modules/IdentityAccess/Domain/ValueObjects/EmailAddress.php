<?php

namespace App\Modules\IdentityAccess\Domain\ValueObjects;

use App\Modules\IdentityAccess\Domain\Exceptions\InvalidIdentity;

final readonly class EmailAddress
{
    public string $value;

    public function __construct(string $value)
    {
        $value = strtolower(trim($value));
        if (strlen($value) > 254 || preg_match('/^[\x21-\x7E]+$/D', $value) !== 1 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidIdentity('A valid ASCII email address of at most 254 characters is required.');
        }
        $this->value = $value;
    }
}
