<?php

namespace Tests\Integration\IdentityAccess;

use App\Modules\IdentityAccess\Application\Contracts\MembershipActor;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\MembershipRepository;
use App\Modules\IdentityAccess\Application\UseCases\ListUserOrganizations;
use App\Modules\IdentityAccess\Application\UseCases\ManageOrganizationMembers;
use App\Modules\IdentityAccess\Application\UseCases\RegisterOrganizationWithOwner;
use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Domain\MembershipStatus;
use App\Modules\IdentityAccess\Infrastructure\Auth\RequestMembershipActor;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\MembershipTestActor;
use Tests\TestCase;

#[Group('security')]
class ManageOrganizationMembersTest extends TestCase
{
    use MembershipTestActor,RefreshDatabase;

    private function service(): ManageOrganizationMembers
    {
        return app(ManageOrganizationMembers::class);
    }

    private function owner(): MembershipRecord
    {
        $member = MembershipRecord::factory()->create(['role' => MembershipRole::Owner]);
        $this->useMembershipActor(UserRecord::query()->findOrFail($member->user_id));

        return $member;
    }

    private function failWith(string $code, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected '.$code);
        } catch (ApplicationFailure $failure) {
            $this->assertSame($code, $failure->errorCode);
        }
    }

    public function test_owner_can_add_list_change_revoke_and_reactivate_existing_user(): void
    {
        $owner = $this->owner();
        $org = OrganizationRecord::query()->findOrFail($owner->organization_id);
        $user = UserRecord::factory()->create();
        $added = $this->service()->add($this->context($org), $user->public_id, MembershipRole::Member);
        $this->assertSame($user->public_id, $added->userPublicId);
        $this->assertCount(2, $this->service()->list($this->context($org)));
        $changed = $this->service()->changeRole($this->context($org), $added->publicId, MembershipRole::Admin, 1);
        $this->assertSame('admin', $changed->role);
        $revoked = $this->service()->revoke($this->context($org), $added->publicId, 2);
        $this->assertSame('revoked', $revoked->status);
        $this->failWith('memberships.duplicate', fn () => $this->service()->add($this->context($org), $user->public_id, MembershipRole::Viewer));
        $active = $this->service()->activate($this->context($org), $added->publicId, 3);
        $this->assertSame('active', $active->status);
        $this->assertSame(4, $active->version);
        $this->assertDatabaseCount('organization_memberships', 2);
        $this->failWith('memberships.concurrent_update', fn () => $this->service()->changeRole($this->context($org), $added->publicId, MembershipRole::Viewer, 1));
    }

    public function test_last_owner_cannot_be_revoked_or_demoted_but_another_active_owner_allows_it(): void
    {
        $owner = $this->owner();
        $org = OrganizationRecord::query()->findOrFail($owner->organization_id);
        $this->failWith('memberships.last_owner', fn () => $this->service()->revoke($this->context($org), $owner->public_id, 1));
        $this->failWith('memberships.last_owner', fn () => $this->service()->changeRole($this->context($org), $owner->public_id, MembershipRole::Admin, 1));
        MembershipRecord::factory()->create(['organization_id' => $org->id, 'role' => MembershipRole::Owner]);
        $changed = $this->service()->changeRole($this->context($org), $owner->public_id, MembershipRole::Viewer, 1);
        $this->assertSame('viewer', $changed->role);
        $this->failWith('tenant.forbidden', fn () => $this->service()->list($this->context($org)));
    }

    public function test_inactive_or_revoked_other_owner_does_not_satisfy_last_owner_guard(): void
    {
        $owner = $this->owner();
        $org = OrganizationRecord::query()->findOrFail($owner->organization_id);
        MembershipRecord::factory()->revoked()->create(['organization_id' => $org->id, 'role' => MembershipRole::Owner]);
        MembershipRecord::factory()->create(['organization_id' => $org->id, 'role' => MembershipRole::Owner, 'user_id' => UserRecord::factory()->inactive()]);
        $this->failWith('memberships.last_owner', fn () => $this->service()->revoke($this->context($org), $owner->public_id, 1));
    }

    public function test_admin_cannot_create_change_revoke_or_reactivate_owner(): void
    {
        $admin = $this->owner();
        $admin->forceFill(['role' => MembershipRole::Admin])->save();
        $org = OrganizationRecord::query()->findOrFail($admin->organization_id);
        $owner = MembershipRecord::factory()->create(['organization_id' => $org->id, 'role' => MembershipRole::Owner]);
        $revoked = MembershipRecord::factory()->revoked()->create(['organization_id' => $org->id, 'role' => MembershipRole::Owner]);
        $user = UserRecord::factory()->create();
        $this->failWith('memberships.forbidden', fn () => $this->service()->add($this->context($org), $user->public_id, MembershipRole::Owner));
        $this->failWith('memberships.forbidden', fn () => $this->service()->changeRole($this->context($org), $admin->public_id, MembershipRole::Owner, 1));
        $this->failWith('memberships.forbidden', fn () => $this->service()->changeRole($this->context($org), $owner->public_id, MembershipRole::Viewer, 1));
        $this->failWith('memberships.forbidden', fn () => $this->service()->revoke($this->context($org), $owner->public_id, 1));
        $this->failWith('memberships.forbidden', fn () => $this->service()->activate($this->context($org), $revoked->public_id, 1));
        $this->assertSame('member', $this->service()->add($this->context($org), $user->public_id, MembershipRole::Member)->role);
    }

    public static function deniedRoles(): array
    {
        return [[MembershipRole::Member], [MembershipRole::Viewer]];
    }

    #[DataProvider('deniedRoles')]
    public function test_non_administrators_cannot_manage_members(MembershipRole $role): void
    {
        $member = $this->owner();
        $member->forceFill(['role' => $role])->save();
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $this->failWith('tenant.forbidden', fn () => $this->service()->list($this->context($org)));
        $this->failWith('tenant.forbidden', fn () => $this->service()->changeRole($this->context($org), $member->public_id, MembershipRole::Owner, 1));
    }

    public function test_scope_revocation_inactive_references_and_user_organizations_are_checked_live(): void
    {
        $owner = $this->owner();
        $org = OrganizationRecord::query()->findOrFail($owner->organization_id);
        UserRecord::query()->whereKey($owner->user_id)->update(['profile' => 'consultant']);
        $this->useMembershipActor(UserRecord::query()->findOrFail($owner->user_id));
        $other = MembershipRecord::factory()->create(['user_id' => $owner->user_id, 'role' => MembershipRole::Viewer]);
        $otherOrg = OrganizationRecord::query()->findOrFail($other->organization_id);
        $listed = app(ListUserOrganizations::class)->execute();
        $this->assertSame(['owner', 'viewer'], array_column($listed, 'role'));
        $this->failWith('tenant.forbidden', fn () => $this->service()->list($this->context($otherOrg)));
        $this->failWith('memberships.not_found', fn () => $this->service()->revoke($this->context($org), $other->public_id, 1));
        $inactive = UserRecord::factory()->inactive()->create();
        $this->failWith('memberships.inactive_user', fn () => $this->service()->add($this->context($org), $inactive->public_id, MembershipRole::Member));
        $org->forceFill(['status' => 'inactive'])->save();
        $this->failWith('tenant.forbidden', fn () => $this->service()->list($this->context($org)));
        $this->assertCount(1, app(ListUserOrganizations::class)->execute());
        $other->forceFill(['status' => MembershipStatus::Revoked, 'revoked_at' => $other->updated_at])->save();
        $this->assertSame([], app(ListUserOrganizations::class)->execute());
    }

    public function test_registration_creates_new_organization_and_first_owner_atomically_for_authenticated_actor(): void
    {
        $user = UserRecord::factory()->create();
        $this->useMembershipActor($user);
        $created = app(RegisterOrganizationWithOwner::class)->execute('Nova empresa');
        $this->assertSame('owner', $created->role);
        $this->assertSame($user->public_id, $created->userPublicId);
        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('organization_memberships', 1);
    }

    public function test_registration_rolls_back_organization_when_membership_creation_fails(): void
    {
        $this->useMembershipActor(UserRecord::factory()->create());
        $repository = Mockery::mock(MembershipRepository::class);
        $repository->shouldReceive('forUser')->once()->andReturn([]);
        $repository->shouldReceive('create')->once()->andThrow(new RuntimeException('simulated failure'));
        app()->instance(MembershipRepository::class, $repository);
        try {
            app(RegisterOrganizationWithOwner::class)->execute('Rollback');
            $this->fail('Expected failure');
        } catch (RuntimeException $failure) {
            $this->assertSame('simulated failure', $failure->getMessage());
        }
        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseCount('organization_memberships', 0);
    }

    public function test_default_actor_adapter_fails_closed_without_validated_session_context(): void
    {
        app()->bind(MembershipActor::class, RequestMembershipActor::class);
        $this->failWith('memberships.unauthenticated', fn () => app(RegisterOrganizationWithOwner::class)->execute('Forbidden'));
        $this->assertDatabaseCount('organizations', 0);
    }
}
