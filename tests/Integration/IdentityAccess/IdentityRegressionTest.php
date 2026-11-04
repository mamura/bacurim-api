<?php

namespace Tests\Integration\IdentityAccess;

use App\Models\User as UserRecord;
use App\Modules\IdentityAccess\Application\Contracts\PasswordChangeAuthorizer;
use App\Modules\IdentityAccess\Application\Contracts\PasswordHasher;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserCredentials;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Application\Data\UserDetails;
use App\Modules\IdentityAccess\Application\Security\PasswordHash;
use App\Modules\IdentityAccess\Application\UseCases\ChangeUserPassword;
use App\Modules\IdentityAccess\Application\UseCases\CreateUser;
use App\Modules\IdentityAccess\Application\UseCases\GetUser;
use App\Modules\IdentityAccess\Application\UseCases\UpdateUser;
use App\Modules\IdentityAccess\Domain\Entities\User;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use App\Modules\IdentityAccess\Infrastructure\Persistence\IdentityPersistenceFailure;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use DateTimeImmutable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Tests\TestCase;

#[Group('security')]
class IdentityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function create(string $email = 'ana@example.com'): UserData
    {
        return $this->app->make(CreateUser::class)->execute(new UserDetails('Ana', $email), 'a long testing passphrase');
    }

    private function allow(): void
    {
        $this->app->instance(PasswordChangeAuthorizer::class, new class implements PasswordChangeAuthorizer
        {
            public function assertMayChange(string $subjectPublicId): void {}
        });
    }

    public function test_email_collision_on_update_preserves_profile_version_and_credentials(): void
    {
        $first = $this->create();
        $second = $this->create('other@example.com');
        $hash = $this->app->make(UserCredentials::class)->getHash($second->publicId, 1)->encoded();
        try {
            $this->app->make(UpdateUser::class)->execute($second->publicId, new UserDetails('Changed', ' ANA@example.com '), 1);
            $this->fail('Expected email conflict.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('identity.email_conflict', $failure->errorCode);
        }
        $this->assertEquals($second, $this->app->make(GetUser::class)->execute($second->publicId));
        $this->assertSame($hash, $this->app->make(UserCredentials::class)->getHash($second->publicId, 1)->encoded());
        $this->assertSame($first->email, $this->app->make(GetUser::class)->execute($first->publicId)->email);
    }

    public function test_profile_updates_reset_verification_only_when_address_changes(): void
    {
        $record = UserRecord::factory()->create(['email' => 'verified@example.com']);
        $first = $this->app->make(UpdateUser::class)->execute($record->public_id, new UserDetails('Name only', 'VERIFIED@example.com'), 1);
        $this->assertNotNull($first->emailVerifiedAt);
        $second = $this->app->make(UpdateUser::class)->execute($record->public_id, new UserDetails('New address', 'changed@example.com'), 2);
        $this->assertNull($second->emailVerifiedAt);
    }

    public static function rejectedPasswords(): array
    {
        return [['short', 'identity.password_policy'], ['a long testing passphrase', 'identity.password_reused']];
    }

    #[DataProvider('rejectedPasswords')]
    public function test_rejected_password_change_does_not_write(#[SensitiveParameter] string $newPassword, string $code): void
    {
        $user = $this->create();
        $this->allow();
        $original = $this->app->make(UserCredentials::class)->getHash($user->publicId, 1)->encoded();
        try {
            $this->app->make(ChangeUserPassword::class)->execute($user->publicId, 'a long testing passphrase', $newPassword, 1);
            $this->fail('Expected rejection.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame($code, $failure->errorCode);
        }
        $this->assertSame($original, $this->app->make(UserCredentials::class)->getHash($user->publicId, 1)->encoded());
    }

    public function test_profile_change_between_password_verification_and_write_rejects_stale_credential_write(): void
    {
        $user = $this->create();
        $this->allow();
        $delegate = $this->app->make(UserCredentials::class);
        $repository = $this->app->make(UserRepository::class);
        $this->app->instance(UserCredentials::class, new class($delegate, $repository) implements UserCredentials
        {
            public function __construct(private UserCredentials $delegate, private UserRepository $repository) {}

            public function getHash(string $publicId, int $expectedVersion): PasswordHash
            {
                return $this->delegate->getHash($publicId, $expectedVersion);
            }

            public function replace(string $publicId, #[SensitiveParameter] PasswordHash $hash, int $expectedVersion, DateTimeImmutable $now): User
            {
                $user = $this->repository->findByPublicId($publicId);
                $this->repository->update($user->updateDetails('Concurrent profile', $user->email, $now));

                return $this->delegate->replace($publicId, $hash, $expectedVersion, $now);
            }
        });
        try {
            $this->app->make(ChangeUserPassword::class)->execute($user->publicId, 'a long testing passphrase', 'a different testing passphrase', 1);
            $this->fail('Expected stale write rejection.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('identity.concurrent_update', $failure->errorCode);
        }
        $current = $this->app->make(GetUser::class)->execute($user->publicId);
        $this->assertSame('Concurrent profile', $current->name);
        $this->assertSame(2, $current->version);
        $this->assertTrue($this->app->make(PasswordHasher::class)->verify('a long testing passphrase', $delegate->getHash($user->publicId, 2)));
    }

    public function test_failed_credential_write_preserves_hash_and_version(): void
    {
        $user = $this->create();
        $this->allow();
        $oldHash = $this->app->make(UserCredentials::class)->getHash($user->publicId, 1)->encoded();
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_probe_version CHECK (version = 1)');
        try {
            $this->app->make(ChangeUserPassword::class)->execute($user->publicId, 'a long testing passphrase', 'a different testing passphrase', 1);
            $this->fail('Expected database failure.');
        } catch (IdentityPersistenceFailure $failure) {
            $this->assertNull($failure->getPrevious());
            $this->assertStringNotContainsString('a different testing passphrase', (string) $failure);
        }
        $this->assertSame($oldHash, $this->app->make(UserCredentials::class)->getHash($user->publicId, 1)->encoded());
        $this->assertEquals($user, $this->app->make(GetUser::class)->execute($user->publicId));
    }

    public function test_missing_users_remain_not_found_and_plaintext_cannot_enter_model(): void
    {
        $this->assertNull($this->app->make(UserRepository::class)->findByEmail(new EmailAddress('missing@example.com')));
        try {
            $this->app->make(GetUser::class)->execute('bad-id');
            $this->fail('Expected not found.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('identity.not_found', $failure->errorCode);
        }
        $record = new UserRecord;
        $this->expectException(\InvalidArgumentException::class);
        $record->forceFill(['password' => 'a plaintext testing passphrase']);
    }

    public function test_real_persistence_error_reaches_logs_and_http_without_credentials(): void
    {
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_probe_logs CHECK (name <> 'Rejected')");
        $plain = 'a distinct testing passphrase';
        $hash = $this->app->make(PasswordHasher::class)->hash($plain);
        try {
            $this->app->make(UserRepository::class)->create(User::create((string) Str::uuid(), 'Rejected', new EmailAddress('error@example.com'), new DateTimeImmutable), $hash);
            $this->fail('Expected persistence failure.');
        } catch (IdentityPersistenceFailure $failure) {
            $logger = $this->createMock(LoggerInterface::class);
            $logger->expects($this->once())->method('error')->with(
                'Identity persistence failed.',
                $this->callback(function (array $context) use ($plain, $hash): bool {
                    $rendered = (string) $context['exception'];
                    $this->assertStringNotContainsString($plain, $rendered);
                    $this->assertStringNotContainsString($hash->encoded(), $rendered);
                    $this->assertNull($context['exception']->getPrevious());

                    return true;
                }),
            );
            $this->app->instance(LoggerInterface::class, $logger);
            $handler = $this->app->make(ExceptionHandler::class);
            $handler->report($failure);
            $response = $handler->render(Request::create('/api/v1/_contract/identity-failure'), $failure);
            $this->assertSame(500, $response->getStatusCode());
            $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
            $this->assertStringNotContainsString($plain, $response->getContent());
            $this->assertStringNotContainsString($hash->encoded(), $response->getContent());
            $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        }
    }
}
