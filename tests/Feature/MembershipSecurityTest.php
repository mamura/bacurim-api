<?php

namespace Tests\Feature;

use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\StatefulBrowser;
use Tests\TestCase;

#[Group('security')]
class MembershipSecurityTest extends TestCase
{
    use RefreshDatabase,StatefulBrowser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureBrowser();
    }

    private function login(UserRecord $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => 'testing-only-password'])->assertOk();
    }

    private function path(OrganizationRecord $org, ?MembershipRecord $member = null): string
    {
        return '/api/v1/organizations/'.$org->public_id.'/members'.($member === null ? '' : '/'.$member->public_id);
    }

    public static function managementMatrix(): array
    {
        return [[MembershipRole::Owner, true], [MembershipRole::Admin, true], [MembershipRole::Member, false], [MembershipRole::Viewer, false]];
    }

    #[DataProvider('managementMatrix')]
    public function test_all_management_endpoints_apply_role_matrix(MembershipRole $role, bool $allowed): void
    {
        $actor = MembershipRecord::factory()->create(['role' => $role]);
        $org = OrganizationRecord::query()->findOrFail($actor->organization_id);
        $target = MembershipRecord::factory()->create(['organization_id' => $org->id, 'role' => MembershipRole::Viewer]);
        $new = UserRecord::factory()->create();
        $this->login(UserRecord::query()->findOrFail($actor->user_id));
        $this->browserRequest('GET', $this->path($org))->assertStatus($allowed ? 200 : 403);
        $this->browserRequest('POST', $this->path($org), ['user_public_id' => $new->public_id, 'role' => 'viewer'])->assertStatus($allowed ? 201 : 403);
        $this->browserRequest('PATCH', $this->path($org, $target), ['version' => 1, 'role' => 'member'])->assertStatus($allowed ? 200 : 403);
        $this->browserRequest('DELETE', $this->path($org, $target), ['version' => $allowed ? 2 : 1])->assertStatus($allowed ? 204 : 403);
        if (! $allowed) {
            $this->assertDatabaseMissing('organization_memberships', ['organization_id' => $org->id, 'user_id' => $new->id]);
            $this->assertSame('active', $target->refresh()->status->value);
            $this->assertSame('viewer', $target->role->value);
        }
    }

    public function test_same_user_owner_and_viewer_roles_do_not_cross_organizations(): void
    {
        $first = MembershipRecord::factory()->create(['role' => MembershipRole::Owner]);
        UserRecord::query()->whereKey($first->user_id)->update(['profile' => 'consultant']);
        $second = MembershipRecord::factory()->create(['user_id' => $first->user_id, 'role' => MembershipRole::Viewer]);
        $firstOrg = OrganizationRecord::query()->findOrFail($first->organization_id);
        $secondOrg = OrganizationRecord::query()->findOrFail($second->organization_id);
        $this->login(UserRecord::query()->findOrFail($first->user_id));
        $this->browserRequest('GET', $this->path($firstOrg))->assertOk();
        $this->browserRequest('GET', $this->path($secondOrg))->assertForbidden();
        $this->browserRequest('PATCH', $this->path($secondOrg, $second), ['version' => 1, 'role' => 'owner', 'actor_role' => 'owner', 'organization_public_id' => $firstOrg->public_id])->assertForbidden();
        $this->browserRequest('GET', '/api/v1/auth/organizations')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.role', 'owner')->assertJsonPath('data.1.role', 'viewer');
        $this->assertSame('viewer', $second->refresh()->role->value);
    }

    public function test_revocation_blocks_next_request_only_in_target_organization_and_reactivation_restores_access(): void
    {
        $member = MembershipRecord::factory()->create(['role' => MembershipRole::Owner]);
        UserRecord::query()->whereKey($member->user_id)->update(['profile' => 'consultant']);
        $other = MembershipRecord::factory()->create(['user_id' => $member->user_id, 'role' => MembershipRole::Owner]);
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $otherOrg = OrganizationRecord::query()->findOrFail($other->organization_id);
        $revoker = MembershipRecord::factory()->create(['organization_id' => $org->id, 'role' => MembershipRole::Owner]);
        $this->login(UserRecord::query()->findOrFail($member->user_id));
        $memberCookies = $this->browserCookies;
        $this->login(UserRecord::query()->findOrFail($revoker->user_id));
        $this->browserRequest('DELETE', $this->path($org, $member), ['version' => 1])->assertNoContent();
        $revokerCookies = $this->browserCookies;
        $this->browserCookies = $memberCookies;
        $this->browserRequest('GET', $this->path($org))->assertForbidden()->assertHeader('Content-Type', 'application/problem+json');
        $this->browserRequest('GET', $this->path($otherOrg))->assertOk();
        $this->browserRequest('GET', '/api/v1/auth/me')->assertOk();
        $this->browserRequest('GET', '/api/v1/auth/organizations')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.public_id', $otherOrg->public_id);
        $memberCookies = $this->browserCookies;
        $this->browserCookies = $revokerCookies;
        $this->browserRequest('PATCH', $this->path($org, $member), ['version' => 2, 'status' => 'active'])->assertOk();
        $this->browserCookies = $memberCookies;
        $this->browserRequest('GET', $this->path($org))->assertOk();
    }

    public function test_missing_membership_and_inactive_organization_never_grant_access(): void
    {
        $member = MembershipRecord::factory()->create(['role' => MembershipRole::Owner]);
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $other = OrganizationRecord::factory()->create();
        $this->login(UserRecord::query()->findOrFail($member->user_id));
        $this->browserRequest('GET', $this->path($other))->assertForbidden();
        $org->forceFill(['status' => 'inactive'])->save();
        $this->browserRequest('GET', $this->path($org))->assertForbidden();
        $this->browserRequest('GET', '/api/v1/auth/organizations')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_inactive_user_loses_session_and_cannot_manage_members(): void
    {
        $member = MembershipRecord::factory()->create(['role' => MembershipRole::Owner]);
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $user = UserRecord::query()->findOrFail($member->user_id);
        $this->login($user);
        $user->forceFill(['status' => 'inactive', 'version' => 2])->save();
        $this->browserRequest('GET', $this->path($org))->assertUnauthorized();
        $this->browserRequest('GET', '/api/v1/auth/organizations')->assertUnauthorized();
    }

    public function test_admin_cannot_reactivate_revoked_owner_or_demote_active_owner(): void
    {
        $admin = MembershipRecord::factory()->create(['role' => MembershipRole::Admin]);
        $org = OrganizationRecord::query()->findOrFail($admin->organization_id);
        $owner = MembershipRecord::factory()->create(['organization_id' => $org->id, 'role' => MembershipRole::Owner]);
        $revoked = MembershipRecord::factory()->revoked()->create(['organization_id' => $org->id, 'role' => MembershipRole::Owner]);
        $this->login(UserRecord::query()->findOrFail($admin->user_id));
        $this->browserRequest('PATCH', $this->path($org, $owner), ['version' => 1, 'role' => 'viewer'])->assertForbidden();
        $this->browserRequest('PATCH', $this->path($org, $revoked), ['version' => 1, 'status' => 'active'])->assertForbidden();
        $this->assertSame('owner', $owner->refresh()->role->value);
        $this->assertSame('revoked', $revoked->refresh()->status->value);
    }

    public function test_inactive_target_cannot_be_reactivated_and_owner_cannot_change_revoked_role(): void
    {
        $owner = MembershipRecord::factory()->create(['role' => MembershipRole::Owner]);
        $org = OrganizationRecord::query()->findOrFail($owner->organization_id);
        $target = MembershipRecord::factory()->revoked()->create(['organization_id' => $org->id, 'user_id' => UserRecord::factory()->inactive()]);
        $this->login(UserRecord::query()->findOrFail($owner->user_id));
        $this->browserRequest('PATCH', $this->path($org, $target), ['version' => 1, 'status' => 'active'])->assertConflict()->assertJsonPath('code', 'memberships.inactive_user');
        $this->browserRequest('PATCH', $this->path($org, $target), ['version' => 1, 'role' => 'viewer'])->assertConflict()->assertJsonPath('code', 'memberships.invalid_transition');
    }

    public static function mutationMethods(): array
    {
        return [['POST'], ['PATCH'], ['DELETE']];
    }

    #[DataProvider('mutationMethods')]
    public function test_every_mutation_requires_csrf_and_authentication(string $method): void
    {
        $owner = MembershipRecord::factory()->create(['role' => MembershipRole::Owner]);
        $org = OrganizationRecord::query()->findOrFail($owner->organization_id);
        $this->login(UserRecord::query()->findOrFail($owner->user_id));
        $path = $this->path($org, $method === 'POST' ? null : $owner);
        $this->browserRequest($method, $path, ['version' => 1, 'role' => 'owner'], csrf: false)->assertStatus(419);
        $this->browserRequest($method, $path, ['version' => 1], ['HTTP_ORIGIN' => 'https://evil.example'])->assertForbidden();
        $this->assertSame(1, $owner->refresh()->version);
    }

    public function test_identifiers_do_not_allow_reassigning_or_reading_foreign_memberships(): void
    {
        $owner = MembershipRecord::factory()->create(['role' => MembershipRole::Owner]);
        $org = OrganizationRecord::query()->findOrFail($owner->organization_id);
        $foreign = MembershipRecord::factory()->create();
        $foreignOrg = OrganizationRecord::query()->findOrFail($foreign->organization_id);
        $this->login(UserRecord::query()->findOrFail($owner->user_id));
        $this->browserRequest('DELETE', $this->path($org, $foreign), ['version' => 1])->assertNotFound();
        $this->browserRequest('POST', $this->path($foreignOrg), ['user_public_id' => UserRecord::query()->findOrFail($owner->user_id)->public_id, 'role' => 'owner'])->assertForbidden();
        $this->assertSame('active', $foreign->refresh()->status->value);
        $this->assertSame(1, $foreign->version);
    }

    public function test_collection_queries_reject_unknown_parameters_instead_of_selecting_tenant(): void
    {
        $owner = MembershipRecord::factory()->create(['role' => MembershipRole::Owner]);
        $org = OrganizationRecord::query()->findOrFail($owner->organization_id);
        $this->login(UserRecord::query()->findOrFail($owner->user_id));
        $this->browserRequest('GET', $this->path($org).'?organization_id=other')->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
        $this->browserRequest('GET', '/api/v1/auth/organizations?page=1')->assertUnprocessable();
    }
}
