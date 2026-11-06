<?php

namespace Tests\Integration\IdentityAccess;

use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantAuthorization;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantCapability;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Application\UseCases\ChangeUserProfile;
use App\Modules\IdentityAccess\Application\UseCases\ResolveTenantContext;
use App\Modules\IdentityAccess\Domain\Entities\User;
use App\Modules\IdentityAccess\Domain\UserProfile;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AllowsProfileChanges;
use Tests\Support\StatefulBrowser;
use Tests\TestCase;

#[Group('security')]
class ChangeUserProfileTest extends TestCase
{
    use AllowsProfileChanges, RefreshDatabase, StatefulBrowser;

    private function failure(string $code, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected '.$code);
        } catch (ApplicationFailure $failure) {
            $this->assertSame($code, $failure->errorCode);
        }
    }

    public static function roles(): array
    {
        return [['owner'], ['admin'], ['member'], ['viewer']];
    }

    #[DataProvider('roles')]
    public function test_no_organization_role_grants_global_profile_changes(string $role): void
    {
        $record = UserRecord::factory()->create();
        MembershipRecord::factory()->create(['user_id' => $record->id, 'role' => $role]);
        $this->configureBrowser();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $record->email, 'password' => 'testing-only-password'])->assertOk();
        $this->failure('identity.profile_forbidden', fn () => app(ChangeUserProfile::class)->execute($record->public_id, UserProfile::Consultant, 1));
        $this->assertSame(UserProfile::Standard, $record->fresh()->profile);
        $this->assertSame(1, $record->fresh()->version);
    }

    public static function allowedCounts(): array
    {
        return [[0], [1]];
    }

    #[DataProvider('allowedCounts')]
    public function test_authorized_downgrade_with_zero_or_one_active_preserves_identity_and_increments_version(int $count): void
    {
        $record = UserRecord::factory()->consultant()->create();
        if ($count === 1) {
            MembershipRecord::factory()->create(['user_id' => $record->id]);
        }
        MembershipRecord::factory()->revoked()->create(['user_id' => $record->id]);
        $before = DB::table('organization_memberships')->orderBy('id')->get()->toJson();
        $this->allowProfileChanges();
        $changed = app(ChangeUserProfile::class)->execute($record->public_id, UserProfile::Standard, 1);
        $this->assertSame(UserProfile::Standard, $changed->profile);
        $this->assertSame(2, $changed->version);
        $this->assertSame($record->password, $record->fresh()->password);
        $this->assertSame($before, DB::table('organization_memberships')->orderBy('id')->get()->toJson());
        $this->failure('identity.concurrent_update', fn () => app(ChangeUserProfile::class)->execute($record->public_id, UserProfile::Consultant, 1));
    }

    public function test_downgrade_with_multiple_active_memberships_is_atomic_even_when_organization_inactive(): void
    {
        $record = UserRecord::factory()->consultant()->create();
        MembershipRecord::factory()->count(2)->create(['user_id' => $record->id]);
        DB::table('organizations')->update(['status' => 'inactive']);
        $before = DB::table('users')->where('id', $record->id)->first();
        $this->allowProfileChanges();
        $this->failure('identity.profile_memberships_conflict', fn () => app(ChangeUserProfile::class)->execute($record->public_id, UserProfile::Standard, 1));
        $this->assertEquals($before, DB::table('users')->where('id', $record->id)->first());
    }

    public function test_profile_change_invalidates_real_session_and_previously_issued_tenant_context(): void
    {
        $record = UserRecord::factory()->consultant()->create();
        $membership = MembershipRecord::factory()->create(['user_id' => $record->id]);
        $org = OrganizationRecord::query()->findOrFail($membership->organization_id);
        $user = app(UserRepository::class)->findByPublicId($record->public_id);
        $context = app(ResolveTenantContext::class)->execute($org->public_id, UserData::fromEntity($user));
        $this->configureBrowser();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $record->email, 'password' => 'testing-only-password'])->assertOk();
        $this->allowProfileChanges();
        app(ChangeUserProfile::class)->execute($record->public_id, UserProfile::Standard, 1);
        $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
        $this->failure('tenant.forbidden', fn () => app(TenantAuthorization::class)->authorize($context, TenantCapability::ReadFinancial));
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $record->email, 'password' => 'testing-only-password'])->assertOk();
    }

    public function test_common_repository_update_cannot_smuggle_a_different_profile(): void
    {
        $record = UserRecord::factory()->create();
        $repository = app(UserRepository::class);
        $user = $repository->findByPublicId($record->public_id);
        $forged = new User($user->id, $user->publicId, $user->name, $user->email, $user->status, $user->emailVerifiedAt, $user->createdAt, $user->updatedAt, $user->version, UserProfile::Consultant);
        $this->failure('identity.profile_forbidden', fn () => $repository->update($forged));
        $this->assertSame(UserProfile::Standard, $record->fresh()->profile);
        $this->expectException(\InvalidArgumentException::class);
        $record->fill(['name' => 'Forged', 'profile' => 'consultant']);
    }

    public function test_http_inputs_reject_arbitrary_profile_in_login_and_member_management(): void
    {
        $owner = UserRecord::factory()->create();
        $member = MembershipRecord::factory()->create(['user_id' => $owner->id, 'role' => 'owner']);
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $this->configureBrowser();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $owner->email, 'password' => 'testing-only-password', 'profile' => 'consultant'])->assertUnprocessable()->assertJsonValidationErrors('profile');
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $owner->email, 'password' => 'testing-only-password'])->assertOk();
        $target = UserRecord::factory()->create();
        $path = '/api/v1/organizations/'.$org->public_id.'/members';
        $this->browserRequest('POST', $path, ['user_public_id' => $target->public_id, 'role' => 'viewer', 'profile' => 'consultant'])->assertUnprocessable()->assertJsonValidationErrors('profile');
        $this->browserRequest('PATCH', $path.'/'.$member->public_id, ['version' => 1, 'role' => 'owner', 'profile' => 'consultant'])->assertUnprocessable()->assertJsonValidationErrors('profile');
        $this->assertSame(UserProfile::Standard, $owner->fresh()->profile);
        $this->assertDatabaseMissing('organization_memberships', ['user_id' => $target->id]);
    }
}
