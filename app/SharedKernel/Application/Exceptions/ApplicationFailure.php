<?php

namespace App\SharedKernel\Application\Exceptions;

use InvalidArgumentException;
use RuntimeException;

final class ApplicationFailure extends RuntimeException
{
    public function __construct(
        public readonly FailureKind $kind,
        public readonly string $errorCode,
        public readonly string $publicMessage,
    ) {
        if (! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/D', $errorCode) || trim($publicMessage) === '') {
            throw new InvalidArgumentException('Application failures require a stable code and a public message.');
        }

        parent::__construct($publicMessage);
    }
}
