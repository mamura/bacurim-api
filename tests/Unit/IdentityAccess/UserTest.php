<?php

namespace Tests\Unit\IdentityAccess;

use App\Modules\IdentityAccess\Domain\Entities\User;
use App\Modules\IdentityAccess\Domain\Exceptions\InvalidIdentity;
use App\Modules\IdentityAccess\Domain\UserStatus;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('security')]
class UserTest extends TestCase
{
    private const UUID = 'c4ca63e2-92a4-4c17-bf34-c08753927800';

    public function test_creation_is_global_active_unverified_and_contains_no_credentials(): void
    {
        $time = new DateTimeImmutable;
        $user = User::create(strtoupper(self::UUID), ' Ana ', new EmailAddress(' Ana.Silva+Finance@EXAMPLE.COM '), $time);
        $this->assertSame('Ana', $user->name);
        $this->assertSame(self::UUID, $user->publicId);
        $this->assertSame('ana.silva+finance@example.com', $user->email->value);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNull($user->id);
        $this->assertNull($user->emailVerifiedAt);
        $this->assertFalse(property_exists($user, 'password'));
        $this->assertFalse(property_exists($user, 'organizationId'));
    }

    public function test_detail_and_status_changes_preserve_identity_and_reset_verification_only_on_email_change(): void
    {
        $time = new DateTimeImmutable('2026-11-04T12:00:00Z');
        $user = new User(1, self::UUID, 'Ana', new EmailAddress('ana@example.com'), UserStatus::Active, $time, $time, $time, 1);
        $later = $time->modify('+1 second');
        $same = $user->updateDetails('New', new EmailAddress('ANA@example.com'), $later);
        $changed = $same->updateDetails('Changed', new EmailAddress('other@example.com'), $later);
        $inactive = $changed->changeStatus(UserStatus::Inactive, $later);
        $active = $inactive->changeStatus(UserStatus::Active, $later);
        $this->assertEquals($time, $same->emailVerifiedAt);
        $this->assertNull($changed->emailVerifiedAt);
        $this->assertSame(UserStatus::Inactive, $inactive->status);
        $this->assertSame(UserStatus::Active, $active->status);
        $this->assertSame(1, $active->id);
        $this->assertSame(1, $active->version);
        $this->assertSame(self::UUID, $active->publicId);
        $this->assertEquals($time, $active->createdAt);
        $this->assertEquals($later, $active->updatedAt);
        $this->assertSame('Ana', $user->name);
    }

    public static function invalidEmails(): array
    {
        return [[''], ['bad'], ['a b@example.com'], ['á@example.com'], ['a@'], [str_repeat('a', 65).'@example.com'], ["a@example.com\nBcc:other@example.com"]];
    }

    #[DataProvider('invalidEmails')]
    public function test_invalid_emails_are_rejected(string $input): void
    {
        $this->expectException(InvalidIdentity::class);
        new EmailAddress($input);
    }

    public static function invalidNames(): array
    {
        return [[''], [' '], ["A\nB"], [str_repeat('á', 256)]];
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_names_are_rejected(string $name): void
    {
        $this->expectException(InvalidIdentity::class);
        User::create(self::UUID, $name, new EmailAddress('a@example.com'), new DateTimeImmutable);
    }

    public function test_unicode_name_boundary_is_accepted(): void
    {
        $this->assertSame(255, mb_strlen(User::create(self::UUID, str_repeat('á', 255), new EmailAddress('a@example.com'), new DateTimeImmutable)->name));
    }

    public function test_updates_cannot_move_time_backwards(): void
    {
        $time = new DateTimeImmutable;
        $user = User::create(self::UUID, 'Ana', new EmailAddress('a@example.com'), $time);
        $this->expectException(InvalidIdentity::class);
        $user->changeStatus(UserStatus::Inactive, $time->modify('-1 second'));
    }

    public function test_invalid_uuid_is_rejected(): void
    {
        $this->expectException(InvalidIdentity::class);
        User::create('1', 'Ana', new EmailAddress('a@example.com'), new DateTimeImmutable);
    }

    public static function invalidPersistenceStates(): array
    {
        return [[null, 1], [1, 0], [0, 1], [1, -1]];
    }

    #[DataProvider('invalidPersistenceStates')]
    public function test_invalid_identity_versions_are_rejected(?int $id, int $version): void
    {
        $time = new DateTimeImmutable;
        $this->expectException(InvalidIdentity::class);
        new User($id, self::UUID, 'Ana', new EmailAddress('a@example.com'), UserStatus::Active, null, $time, $time, $version);
    }

    public function test_verification_cannot_precede_creation(): void
    {
        $time = new DateTimeImmutable;
        $this->expectException(InvalidIdentity::class);
        new User(1, self::UUID, 'Ana', new EmailAddress('a@example.com'), UserStatus::Active, $time->modify('-1 second'), $time, $time, 1);
    }
}
