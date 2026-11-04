<?php

namespace App\Modules\IdentityAccess\Application\Security;

use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use SensitiveParameter;

final readonly class PasswordHash implements JsonSerializable
{
    private string $value;

    public function __construct(#[SensitiveParameter] string $value)
    {
        if (! in_array(password_get_info($value)['algoName'], ['bcrypt', 'argon2id'], true)) {
            throw new InvalidArgumentException('An encoded bcrypt or Argon2id hash is required.');
        }
        $this->value = $value;
    }

    public function encoded(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return '[REDACTED]';
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['passwordHash' => '[REDACTED]'];
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new LogicException('Credential serialization is forbidden.');
    }
}
