<?php

namespace Tests\Unit\Security;

use App\Logging\RedactSensitiveLog;
use DateTimeImmutable;
use Illuminate\Container\Container;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('security')]
class LogRedactionTest extends TestCase
{
    public function test_nested_keys_hashes_bearer_values_objects_and_exception_arguments_are_not_serialized(): void
    {
        $hash = password_hash('testing-secret', PASSWORD_BCRYPT, ['cost' => 4]);
        $setting = ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
        try {
            $exception = $this->exceptionWithSensitiveArgument('exception-argument-sentinel');
        } finally {
            ini_set('zend.exception_ignore_args', $setting);
        }
        $this->assertSame(['exception-argument-sentinel'], $exception->getTrace()[0]['args']);
        $record = new LogRecord(new DateTimeImmutable, 'security', Level::Error, 'Hash '.$hash.' Bearer bearer-value-sentinel',
            ['nested' => ['new_password' => 'nested-secret', 'refresh_token' => 'refresh-secret'], 'exception' => $exception, 'opaque' => (object) ['credential' => 'object-secret'], 'operation' => 'safe-operation']);
        $clean = (new RedactSensitiveLog(new Container))($record);
        $encoded = json_encode($clean, JSON_THROW_ON_ERROR);
        foreach ([$hash, 'bearer-value-sentinel', 'nested-secret', 'refresh-secret', 'exception-argument-sentinel', 'object-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
        $this->assertStringContainsString('safe-operation', $encoded);
        $this->assertStringContainsString(RuntimeException::class, $encoded);
        $this->assertArrayNotHasKey('args', $clean->context['exception']['trace'][0]);
    }

    private function exceptionWithSensitiveArgument(string $secret): RuntimeException
    {
        return new RuntimeException('Safe diagnostic');
    }

    public function test_recursive_context_is_bounded_and_never_dumped(): void
    {
        $recursive = ['password' => 'recursive-secret'];
        $recursive['again'] = &$recursive;
        $record = new LogRecord(new DateTimeImmutable, 'security', Level::Error, 'Safe', ['recursive' => $recursive]);
        $encoded = json_encode((new RedactSensitiveLog(new Container))($record), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('recursive-secret', $encoded);
        $this->assertStringContainsString('[REDACTED:depth]', $encoded);
    }
}
