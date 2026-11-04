<?php

namespace Tests\Unit\IdentityAccess;

use App\Modules\IdentityAccess\Application\Contracts\IdentityClock;
use App\Modules\IdentityAccess\Application\Contracts\IdentityIdGenerator;
use App\Modules\IdentityAccess\Application\Contracts\PasswordChangeAuthorizer;
use App\Modules\IdentityAccess\Application\Contracts\PasswordHasher;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserCredentials;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Application\Data\UserDetails;
use App\Modules\IdentityAccess\Application\Security\PasswordHash;
use App\Modules\IdentityAccess\Application\UseCases\ChangeUserPassword;
use App\Modules\IdentityAccess\Application\UseCases\CreateUser;
use App\Modules\IdentityAccess\Application\UseCases\UpdateUser;
use App\Modules\IdentityAccess\Application\UserLookup;
use App\Modules\IdentityAccess\Domain\Entities\User;
use App\Modules\IdentityAccess\Domain\UserStatus;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('security')]
class UserUseCasesTest extends TestCase
{
    private const UUID = 'c4ca63e2-92a4-4c17-bf34-c08753927800';

    private function clock(): IdentityClock
    {
        return new class implements IdentityClock
        {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-04T14:00:00Z');
            }
        };
    }

    public function test_creation_uses_ports_and_never_returns_credentials(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $hasher = $this->createMock(PasswordHasher::class);
        $hash = new PasswordHash(password_hash('test-only-passphrase', PASSWORD_BCRYPT, ['cost' => 4]));
        $hasher->expects($this->once())->method('hash')->with('test-only-passphrase')->willReturn($hash);
        $repository->expects($this->once())->method('create')->willReturnCallback(function (User $user, PasswordHash $received) use ($hash): User {
            $this->assertSame($hash, $received);
            $this->assertSame('ana+tag@example.com', $user->email->value);
            $this->assertSame('Ana', $user->name);
            $this->assertEquals($this->clock()->now(), $user->createdAt);

            return new User(1, $user->publicId, $user->name, $user->email, $user->status, null, $user->createdAt, $user->updatedAt, 1);
        });
        $ids = $this->createMock(IdentityIdGenerator::class);
        $ids->method('generate')->willReturn(self::UUID);
        $data = (new CreateUser($repository, $this->clock(), $ids, $hasher))->execute(new UserDetails(' Ana ', ' ANA+tag@EXAMPLE.COM '), 'test-only-passphrase');
        $this->assertSame(self::UUID, $data->publicId);
        $this->assertFalse(property_exists($data, 'password'));
        $this->assertFalse(property_exists($data, 'id'));
        $this->assertStringNotContainsString($hash->encoded(), json_encode($data));
    }

    public function test_invalid_profile_never_hashes_or_persists(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->never())->method('create');
        $hasher = $this->createMock(PasswordHasher::class);
        $hasher->expects($this->never())->method('hash');
        $ids = $this->createMock(IdentityIdGenerator::class);
        $ids->method('generate')->willReturn(self::UUID);
        $this->expectException(ApplicationFailure::class);
        (new CreateUser($repository, $this->clock(), $ids, $hasher))->execute(new UserDetails('', 'a@example.com'), 'test-only-passphrase');
    }

    public function test_denied_password_change_never_reads_user_or_credentials(): void
    {
        $authorizer = $this->createMock(PasswordChangeAuthorizer::class);
        $authorizer->expects($this->once())->method('assertMayChange')->with(self::UUID)->willThrowException(new ApplicationFailure(FailureKind::Forbidden, 'identity.forbidden', 'Forbidden.'));
        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->never())->method('findByPublicId');
        $credentials = $this->createMock(UserCredentials::class);
        $credentials->expects($this->never())->method('getHash');
        $credentials->expects($this->never())->method('replace');
        $hasher = $this->createMock(PasswordHasher::class);
        $hasher->expects($this->never())->method('hash');
        $this->expectException(ApplicationFailure::class);
        (new ChangeUserPassword($authorizer, new UserLookup($repository), $credentials, $hasher, $this->clock()))->execute(self::UUID, 'current testing passphrase', 'new testing passphrase', 1);
    }

    public function test_profile_changes_use_clock_and_clear_old_email_verification(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $time = new DateTimeImmutable('2026-11-04T12:00:00Z');
        $user = new User(1, self::UUID, 'Ana', new EmailAddress('a@example.com'), UserStatus::Active, $time, $time, $time, 1);
        $repository->method('findByPublicId')->willReturn($user);
        $repository->expects($this->once())->method('update')->willReturnCallback(function (User $updated) use ($time): User {
            $this->assertNull($updated->emailVerifiedAt);
            $this->assertEquals($time, $updated->createdAt);
            $this->assertEquals($this->clock()->now(), $updated->updatedAt);
            $this->assertSame(1, $updated->id);

            return $updated;
        });
        (new UpdateUser($repository, new UserLookup($repository), $this->clock()))->execute(self::UUID, new UserDetails('New', 'b@example.com'), 1);
    }
}
