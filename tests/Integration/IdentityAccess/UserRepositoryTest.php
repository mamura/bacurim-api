<?php

namespace Tests\Integration\IdentityAccess;

use App\Models\User as LegacyUser;
use App\Modules\IdentityAccess\Application\Contracts\PasswordHasher;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserCredentials;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Domain\Entities\User;
use App\Modules\IdentityAccess\Domain\UserStatus;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use App\Modules\IdentityAccess\Infrastructure\Persistence\IdentityPersistenceFailure;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('security')]
class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): UserRepository
    {
        return $this->app->make(UserRepository::class);
    }

    private function create(string $email = 'ana@example.com'): User
    {
        return $this->repository()->create(User::create((string) Str::uuid(), ' Ana ', new EmailAddress($email), new DateTimeImmutable('2026-11-04T12:00:00.123456Z')), $this->app->make(PasswordHasher::class)->hash('a long testing passphrase'));
    }

    public function test_domain_round_trip_email_lookup_and_model_serialization(): void
    {
        $user = $this->create(' Ana+tag@EXAMPLE.COM ');
        $this->assertEquals($user, $this->repository()->findByPublicId(strtoupper($user->publicId)));
        $this->assertEquals($user, $this->repository()->findByEmail(new EmailAddress('ANA+tag@example.com')));
        $this->assertSame('123456', $user->createdAt->format('u'));
        $this->assertSame(1, $user->version);
        $serialized = LegacyUser::query()->findOrFail($user->id)->toArray();
        $this->assertArrayNotHasKey('password', $serialized);
        $this->assertArrayNotHasKey('remember_token', $serialized);
        $this->assertNull($this->repository()->findByPublicId('bad-id'));
        $this->assertNull($this->repository()->findByEmail(new EmailAddress('absent@example.com')));
    }

    public function test_normalized_email_collision_is_a_safe_conflict_and_rolls_back(): void
    {
        $this->create();
        try {
            $this->create(' ANA@example.com ');
            $this->fail('Expected conflict.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('identity.email_conflict', $failure->errorCode);
        }
        $this->assertDatabaseCount('users', 1);
        $this->create('another@example.com');
        $this->assertDatabaseCount('users', 2);
    }

    public function test_profile_and_status_updates_preserve_credentials_and_reject_stale_snapshots(): void
    {
        $user = $this->create();
        $credentials = $this->app->make(UserCredentials::class);
        $originalHash = $credentials->getHash($user->publicId, 1)->encoded();
        $updated = $this->repository()->update($user->updateDetails('New', new EmailAddress('new@example.com'), $user->updatedAt)->changeStatus(UserStatus::Inactive, $user->updatedAt));
        $this->assertSame(UserStatus::Inactive, $updated->status);
        $this->assertSame($originalHash, $credentials->getHash($user->publicId, 2)->encoded());
        try {
            $this->repository()->update($user);
            $this->fail('Expected conflict.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('identity.concurrent_update', $failure->errorCode);
        }
        $this->assertSame('New', $this->repository()->findByPublicId($user->publicId)->name);
    }

    public function test_credential_replacement_increments_shared_version_and_clears_remember_token(): void
    {
        $user = $this->create();
        DB::table('users')->where('id', $user->id)->update(['remember_token' => 'test-only-token']);
        $hasher = $this->app->make(PasswordHasher::class);
        $credentials = $this->app->make(UserCredentials::class);
        $updated = $credentials->replace($user->publicId, $hasher->hash('another long testing phrase'), 1, $user->updatedAt->modify('+1 second'));
        $this->assertSame(2, $updated->version);
        $this->assertTrue($hasher->verify('another long testing phrase', $credentials->getHash($user->publicId, 2)));
        $this->assertNull(DB::table('users')->where('id', $user->id)->value('remember_token'));
        $this->expectException(ApplicationFailure::class);
        $credentials->getHash($user->publicId, 1);
    }

    public function test_database_failure_does_not_leak_hash_bindings_and_transaction_recovers(): void
    {
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_probe CHECK (name <> 'Rejected')");
        $hasher = $this->app->make(PasswordHasher::class);
        $hash = $hasher->hash('a long testing passphrase');
        try {
            $this->repository()->create(User::create((string) Str::uuid(), 'Rejected', new EmailAddress('rejected@example.com'), new DateTimeImmutable), $hash);
            $this->fail('Expected persistence failure.');
        } catch (IdentityPersistenceFailure $failure) {
            $this->assertSame('Identity persistence failed.', $failure->getMessage());
            $this->assertNull($failure->getPrevious());
            $this->assertStringNotContainsString($hash->encoded(), (string) $failure);
        }
        $this->assertDatabaseCount('users', 0);
        $this->create();
        $this->assertDatabaseCount('users', 1);
    }
}
