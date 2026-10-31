<?php

namespace App\SharedKernel\Application\Exceptions;

enum FailureKind
{
    case Validation;
    case Conflict;
    case NotFound;
    case Forbidden;
}
