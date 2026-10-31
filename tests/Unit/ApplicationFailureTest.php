<?php

namespace Tests\Unit;

use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ApplicationFailureTest extends TestCase
{
    public function test_failure_exposes_the_explicit_public_contract(): void
    {
        $failure = new ApplicationFailure(FailureKind::Conflict, 'example.conflict', 'The operation conflicts with the current state.');

        $this->assertSame(FailureKind::Conflict, $failure->kind);
        $this->assertSame('example.conflict', $failure->errorCode);
        $this->assertSame('The operation conflicts with the current state.', $failure->publicMessage);
    }

    public function test_invalid_error_code_cannot_be_used_as_a_public_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ApplicationFailure(FailureKind::Conflict, 'invalid code?secret', 'A safe message.');
    }
}
