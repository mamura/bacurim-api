<?php

namespace Tests\Integration\IdentityAccess;

use App\Modules\IdentityAccess\Application\UseCases\ManageOrganizationMembers;
use App\Modules\IdentityAccess\Application\UseCases\RegisterOrganizationWithOwner;
use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MembershipTestActor;
use Tests\TestCase;

#[Group('security')]
class MembershipCardinalityTest extends TestCase
{
    use MembershipTestActor, RefreshDatabase;

    private function owner(OrganizationRecord $org): void
    {
        $owner = UserRecord::factory()->create();
        MembershipRecord::factory()->create(['user_id' => $owner->id, 'organization_id' => $org->id, 'role' => 'owner']);
        $this->useMembershipActor($owner);
    }

    private function limited(callable $operation): void
    {
        $before = DB::table('organization_memberships')->orderBy('id')->get()->toJson();
        try {
            $operation();
            $this->fail('Expected active limit.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('memberships.active_limit', $failure->errorCode);
        }
        $this->assertSame($before, DB::table('organization_memberships')->orderBy('id')->get()->toJson());
    }

    public function test_standard_add_and_reactivation_count_active_even_in_inactive_organization(): void
    {
        $user = UserRecord::factory()->create();
        $first = MembershipRecord::factory()->create(['user_id' => $user->id]);
        OrganizationRecord::query()->whereKey($first->organization_id)->update(['status' => 'inactive']);
        $org = OrganizationRecord::factory()->create();
        $this->owner($org);
        $service = app(ManageOrganizationMembers::class);
        $this->limited(fn () => $service->add($this->context($org), $user->public_id, MembershipRole::Viewer));
        $revoked = MembershipRecord::factory()->revoked()->create(['user_id' => $user->id, 'organization_id' => $org->id]);
        $this->limited(fn () => $service->activate($this->context($org), $revoked->public_id, 1));
    }

    public function test_revoked_history_does_not_block_first_active_and_reactivation_preserves_role(): void
    {
        $user = UserRecord::factory()->create();
        MembershipRecord::factory()->revoked()->create(['user_id' => $user->id]);
        $org = OrganizationRecord::factory()->create();
        $this->owner($org);
        $service = app(ManageOrganizationMembers::class);
        $added = $service->add($this->context($org), $user->public_id, MembershipRole::Viewer);
        $this->assertSame('active', $added->status);
        $revoked = $service->revoke($this->context($org), $added->publicId, 1);
        $active = $service->activate($this->context($org), $added->publicId, $revoked->version);
        $this->assertSame('viewer', $active->role);
        $this->assertSame(1, MembershipRecord::query()->where('user_id', $user->id)->where('status', 'active')->count());
    }

    public function test_consultant_can_add_and_reactivate_authorized_memberships(): void
    {
        $user = UserRecord::factory()->consultant()->create();
        MembershipRecord::factory()->create(['user_id' => $user->id]);
        $org = OrganizationRecord::factory()->create();
        $this->owner($org);
        $service = app(ManageOrganizationMembers::class);
        $added = $service->add($this->context($org), $user->public_id, MembershipRole::Member);
        $revoked = $service->revoke($this->context($org), $added->publicId, 1);
        $service->activate($this->context($org), $added->publicId, $revoked->version);
        $this->assertSame(2, MembershipRecord::query()->where('user_id', $user->id)->where('status', 'active')->count());
    }

    public function test_standard_registration_rolls_back_without_orphan_and_consultant_can_register(): void
    {
        $user = UserRecord::factory()->create();
        MembershipRecord::factory()->create(['user_id' => $user->id]);
        $this->useMembershipActor($user);
        $count = OrganizationRecord::query()->count();
        $this->limited(fn () => app(RegisterOrganizationWithOwner::class)->execute('Must not exist'));
        $this->assertSame($count, OrganizationRecord::query()->count());
        $user->forceFill(['profile' => 'consultant'])->save();
        $this->useMembershipActor($user->fresh());
        $created = app(RegisterOrganizationWithOwner::class)->execute('Consultant organization');
        $this->assertSame('owner', $created->role);
        $this->assertSame($count + 1, OrganizationRecord::query()->count());
    }
}
