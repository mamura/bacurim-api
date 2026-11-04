<?php

namespace Tests\Unit\IdentityAccess;

use App\Modules\IdentityAccess\Application\Security\PasswordHash;
use App\Modules\IdentityAccess\Application\Security\PasswordPolicy;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('security')]
class PasswordPolicyTest extends TestCase
{
    public static function invalidPasswords(): array
    {
        return [['short'], [str_repeat('a', 14)], [str_repeat('a', 129)], [str_repeat(' ', 15)], ["long-password\0with-null"], ['passwordpassword'], ["\xff".str_repeat('a', 15)]];
    }

    #[DataProvider('invalidPasswords')]
    public function test_policy_rejects_invalid_input_without_echoing_it(#[\SensitiveParameter] string $password): void
    {
        try {
            (new PasswordPolicy)->validate($password);
            $this->fail('Expected validation failure.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('identity.password_policy', $failure->errorCode);
            $this->assertStringNotContainsString($password, $failure->publicMessage);
        }
    }

    public function test_long_passphrases_unicode_and_spaces_are_allowed(): void
    {
        foreach ([str_repeat('á', 15), str_repeat('b', 128), ' a sufficiently long passphrase '] as $password) {
            (new PasswordPolicy)->validate($password);
        }
        $this->addToAssertionCount(3);
    }

    public function test_plain_text_cannot_be_wrapped_as_a_hash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PasswordHash('not-an-encoded-hash');
    }
}
