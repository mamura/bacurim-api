<?php

namespace Tests\Integration\Security;

use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantAuthorization;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantCapability;
use App\Modules\IdentityAccess\Application\UseCases\ManageOrganizationMembers;
use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('security')]
class RoleSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function context(MembershipRecord $member): TenantContext
    {
        $member->refresh();
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $user = UserRecord::query()->findOrFail($member->user_id);

        return new TenantContext($org->id, $org->public_id, $user->public_id, $user->version, $member->public_id, $member->version);
    }

    private function service(): ManageOrganizationMembers
    {
        return app(ManageOrganizationMembers::class);
    }

    private function deniedWithoutMutation(callable $operation, FailureKind $kind = FailureKind::Forbidden): void
    {
        $before = DB::table('organization_memberships')->orderBy('id')->get()->toJson();
        try {
            $operation();
            $this->fail('Expected authorization denial');
        } catch (ApplicationFailure $failure) {
            $this->assertSame($kind, $failure->kind);
        }
        $this->assertSame($before, DB::table('organization_memberships')->orderBy('id')->get()->toJson());
    }

    public static function fixedRoles(): array
    {
        return array_map(fn ($role) => [$role], MembershipRole::cases());
    }

    #[DataProvider('fixedRoles')]
    public function test_owner_can_assign_each_fixed_role_to_an_existing_user(MembershipRole $role): void
    {
        $owner = MembershipRecord::factory()->create(['role' => MembershipRole::Owner]);
        $context = $this->context($owner);
        $user = UserRecord::factory()->create();
        $result = $this->service()->add($context, $user->public_id, $role);
        $this->assertSame($role->value, $result->role);
        $this->assertDatabaseHas('organization_memberships', ['public_id' => $result->publicId, 'organization_id' => $owner->organization_id, 'user_id' => $user->id, 'role' => $role->value]);
    }

    #[DataProvider('fixedRoles')]
    public function test_admin_cannot_modify_owner_to_any_role_and_denial_is_atomic(MembershipRole $requested): void
    {
        $admin = MembershipRecord::factory()->create(['role' => MembershipRole::Admin]);
        $owner = MembershipRecord::factory()->create(['organization_id' => $admin->organization_id, 'role' => MembershipRole::Owner]);
        $this->deniedWithoutMutation(fn () => $this->service()->changeRole($this->context($admin), $owner->public_id, $requested, 1));
    }

    public static function escalations(): array
    {
        return [[MembershipRole::Member, MembershipRole::Admin], [MembershipRole::Member, MembershipRole::Owner], [MembershipRole::Viewer, MembershipRole::Admin], [MembershipRole::Viewer, MembershipRole::Owner]];
    }

    #[DataProvider('escalations')]
    public function test_non_administrators_cannot_promote_themselves_or_add_another_user(MembershipRole $role, MembershipRole $requested): void
    {
        $actor = MembershipRecord::factory()->create(['role' => $role]);
        $context = $this->context($actor);
        $this->deniedWithoutMutation(fn () => $this->service()->changeRole($context, $actor->public_id, $requested, 1));
        $user = UserRecord::factory()->create();
        $this->deniedWithoutMutation(fn () => $this->service()->add($context, $user->public_id, $requested));
        app(TenantAuthorization::class)->authorize($context, TenantCapability::ReadFinancial);
    }

    public function test_last_owner_failures_preserve_role_status_and_version(): void
    {
        $owner = MembershipRecord::factory()->create(['role' => MembershipRole::Owner]);
        $context = $this->context($owner);
        $this->deniedWithoutMutation(fn () => $this->service()->revoke($context, $owner->public_id, 1), FailureKind::Conflict);
        $this->deniedWithoutMutation(fn () => $this->service()->changeRole($context, $owner->public_id, MembershipRole::Admin, 1), FailureKind::Conflict);
        $this->assertSame(1, $owner->refresh()->version);
    }

    public function test_reactivation_does_not_restore_old_context_even_with_the_same_role(): void
    {
        $owner = MembershipRecord::factory()->create(['role' => MembershipRole::Owner]);
        $admin = MembershipRecord::factory()->create(['organization_id' => $owner->organization_id, 'role' => MembershipRole::Admin]);
        $ownerContext = $this->context($owner);
        $oldAdminContext = $this->context($admin);
        $this->service()->revoke($ownerContext, $admin->public_id, 1);
        $restored = $this->service()->activate($ownerContext, $admin->public_id, 2);
        $this->assertSame('admin', $restored->role);
        $this->deniedWithoutMutation(fn () => $this->service()->list($oldAdminContext));
        $this->assertCount(2, $this->service()->list($this->context($admin)));
    }

    public function test_owner_demotion_keeps_another_owner_and_requires_fresh_context(): void
    {
        $owner = MembershipRecord::factory()->create(['role' => MembershipRole::Owner]);
        MembershipRecord::factory()->create(['organization_id' => $owner->organization_id, 'role' => MembershipRole::Owner]);
        $old = $this->context($owner);
        $this->service()->changeRole($old, $owner->public_id, MembershipRole::Admin, 1);
        $this->deniedWithoutMutation(fn () => $this->service()->list($old));
        $this->assertCount(2, $this->service()->list($this->context($owner)));
        $this->assertSame(1, MembershipRecord::query()->where('organization_id', $owner->organization_id)->where('role', 'owner')->where('status', 'active')->count());
    }
}
