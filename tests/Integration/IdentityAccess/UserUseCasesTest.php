<?php

namespace Tests\Integration\IdentityAccess;

use App\Modules\IdentityAccess\Application\Contracts\PasswordChangeAuthorizer;
use App\Modules\IdentityAccess\Application\Contracts\PasswordHasher;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserCredentials;
use App\Modules\IdentityAccess\Application\Data\UserDetails;
use App\Modules\IdentityAccess\Application\UseCases\ChangeUserPassword;
use App\Modules\IdentityAccess\Application\UseCases\ChangeUserStatus;
use App\Modules\IdentityAccess\Application\UseCases\CreateUser;
use App\Modules\IdentityAccess\Application\UseCases\GetUser;
use App\Modules\IdentityAccess\Application\UseCases\UpdateUser;
use App\Modules\IdentityAccess\Domain\UserStatus;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserUseCasesTest extends TestCase
{
    use RefreshDatabase;

    private function allowPasswordChangeFor(string $id): void
    {
        $this->app->instance(PasswordChangeAuthorizer::class, new class($id) implements PasswordChangeAuthorizer
        {
            public function __construct(private string $id) {}

            public function assertMayChange(string $subjectPublicId): void
            {
                if ($this->id !== $subjectPublicId) {
                    throw new ApplicationFailure(FailureKind::Forbidden, 'identity.forbidden', 'Forbidden.');
                }
            }
        });
    }

    public function test_internal_user_lifecycle_and_credential_free_outputs(): void
    {
        $created = $this->app->make(CreateUser::class)->execute(new UserDetails(' Ana ', ' ANA+tag@EXAMPLE.COM '), 'a long testing passphrase');
        $this->assertSame('ana+tag@example.com', $created->email);
        $this->assertNull($created->emailVerifiedAt);
        $updated = $this->app->make(UpdateUser::class)->execute($created->publicId, new UserDetails('New', 'other@example.com'), 1);
        $inactive = $this->app->make(ChangeUserStatus::class)->execute($created->publicId, UserStatus::Inactive, 2);
        $active = $this->app->make(ChangeUserStatus::class)->execute($created->publicId, UserStatus::Active, 3);
        $this->assertSame('New', $updated->name);
        $this->assertSame(UserStatus::Inactive, $inactive->status);
        $this->assertSame(UserStatus::Active, $active->status);
        $this->assertSame(4, $active->version);
        $this->assertEquals($created->createdAt, $active->createdAt);
        $this->assertEquals($active, $this->app->make(GetUser::class)->execute($created->publicId));
        $this->assertStringNotContainsString('password', json_encode($active));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_creation_never_writes(): void
    {
        try {
            $this->app->make(CreateUser::class)->execute(new UserDetails('Ana', 'invalid'), 'a long testing passphrase');
            $this->fail('Expected validation failure.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('identity.invalid', $failure->errorCode);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_changes_are_denied_by_default_even_for_valid_users(): void
    {
        $user = $this->app->make(CreateUser::class)->execute(new UserDetails('Ana', 'a@example.com'), 'a long testing passphrase');
        $this->expectException(ApplicationFailure::class);
        $this->expectExceptionMessage('An authenticated password change flow is required.');
        $this->app->make(ChangeUserPassword::class)->execute($user->publicId, 'a long testing passphrase', 'another long testing passphrase', 1);
    }

    public function test_authorized_change_requires_current_password_and_invalidates_old_hash(): void
    {
        $user = $this->app->make(CreateUser::class)->execute(new UserDetails('Ana', 'a@example.com'), 'a long testing passphrase');
        $this->allowPasswordChangeFor($user->publicId);
        try {
            $this->app->make(ChangeUserPassword::class)->execute($user->publicId, 'wrong-password', 'another long testing passphrase', 1);
            $this->fail('Expected rejection.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('identity.current_password_invalid', $failure->errorCode);
        }
        $this->assertSame(1, $this->app->make(GetUser::class)->execute($user->publicId)->version);
        $changed = $this->app->make(ChangeUserPassword::class)->execute($user->publicId, 'a long testing passphrase', 'another long testing passphrase', 1);
        $this->assertSame(2, $changed->version);
        $hash = $this->app->make(UserCredentials::class)->getHash($user->publicId, 2);
        $this->assertTrue($this->app->make(PasswordHasher::class)->verify('another long testing passphrase', $hash));
        $this->assertFalse($this->app->make(PasswordHasher::class)->verify('a long testing passphrase', $hash));
        $this->assertStringNotContainsString($hash->encoded(), json_encode($changed));
    }

    public function test_inactive_user_cannot_change_password_even_with_authorizer_permission(): void
    {
        $user = $this->app->make(CreateUser::class)->execute(new UserDetails('Ana', 'a@example.com'), 'a long testing passphrase');
        $this->app->make(ChangeUserStatus::class)->execute($user->publicId, UserStatus::Inactive, 1);
        $this->allowPasswordChangeFor($user->publicId);
        try {
            $this->app->make(ChangeUserPassword::class)->execute($user->publicId, 'a long testing passphrase', 'another long testing passphrase', 2);
            $this->fail('Expected inactive rejection.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('identity.inactive', $failure->errorCode);
        }
        $this->assertSame(2, $this->app->make(GetUser::class)->execute($user->publicId)->version);
    }
}
