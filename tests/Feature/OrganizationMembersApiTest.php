<?php

namespace Tests\Feature;

use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\StatefulBrowser;
use Tests\TestCase;

#[Group('security')]
class OrganizationMembersApiTest extends TestCase
{
    use RefreshDatabase,StatefulBrowser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureBrowser();
    }

    protected function loginAsUser(UserRecord $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => 'testing-only-password'])->assertOk();
    }

    protected function owner(): array
    {
        $owner = MembershipRecord::factory()->create(['role' => MembershipRole::Owner]);
        $this->loginAsUser(UserRecord::query()->findOrFail($owner->user_id));

        return [$owner, OrganizationRecord::query()->findOrFail($owner->organization_id)];
    }

    protected function path(OrganizationRecord $org, ?string $member = null): string
    {
        return '/api/v1/organizations/'.$org->public_id.'/members'.($member === null ? '' : '/'.$member);
    }

    public function test_owner_can_manage_existing_user_and_responses_expose_only_public_contract(): void
    {
        [$owner,$org] = $this->owner();
        $user = UserRecord::factory()->create();
        $created = $this->browserRequest('POST', $this->path($org), ['user_public_id' => $user->public_id, 'role' => 'member']);
        $created->assertCreated()->assertJsonPath('data.user.public_id', $user->public_id)->assertJsonPath('data.role', 'member')->assertJsonPath('data.version', 1);
        $this->assertSame(['public_id', 'name'], array_keys($created->json('data.user')));
        $this->assertArrayNotHasKey('id', $created->json('data'));
        $this->assertStringNotContainsString($user->email, $created->getContent());
        $this->assertStringContainsString('no-store', $created->headers->get('Cache-Control'));
        $member = $created->json('data.public_id');
        $created->assertHeader('Location', $this->path($org, $member));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $created->json('data.created_at'));
        $this->browserRequest('GET', $this->path($org))->assertOk()->assertJsonCount(2, 'data');
        $this->browserRequest('PATCH', $this->path($org, $member), ['version' => 1, 'role' => 'admin'])->assertOk()->assertJsonPath('data.version', 2);
        $this->browserRequest('DELETE', $this->path($org, $member), ['version' => 2])->assertNoContent();
        $this->assertDatabaseHas('organization_memberships', ['public_id' => $member, 'status' => 'revoked']);
        $this->browserRequest('PATCH', $this->path($org, $member), ['version' => 3, 'status' => 'active'])->assertOk()->assertJsonPath('data.role', 'admin')->assertJsonPath('data.version', 4);
        $this->browserRequest('GET', '/api/v1/auth/organizations')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.public_id', $org->public_id)->assertJsonPath('data.0.role', 'owner');
    }

    public function test_last_owner_duplicate_and_stale_version_return_problem_details(): void
    {
        [$owner,$org] = $this->owner();
        $this->browserRequest('DELETE', $this->path($org, $owner->public_id), ['version' => 1])->assertConflict()->assertJsonPath('code', 'memberships.last_owner')->assertHeader('Content-Type', 'application/problem+json');
        $this->browserRequest('PATCH', $this->path($org, $owner->public_id), ['version' => 1, 'role' => 'viewer'])->assertConflict()->assertJsonPath('code', 'memberships.last_owner');
        $user = UserRecord::query()->findOrFail($owner->user_id);
        $this->browserRequest('POST', $this->path($org), ['user_public_id' => $user->public_id, 'role' => 'member'])->assertConflict()->assertJsonPath('code', 'memberships.duplicate');
        $this->browserRequest('PATCH', $this->path($org, $owner->public_id), ['version' => 999, 'role' => 'owner'])->assertConflict()->assertJsonPath('code', 'memberships.concurrent_update');
    }

    public function test_admin_cannot_grant_or_change_owner_and_foreign_membership_is_not_found(): void
    {
        [$admin,$org] = $this->owner();
        $admin->forceFill(['role' => MembershipRole::Admin])->save();
        $user = UserRecord::factory()->create();
        $owner = MembershipRecord::factory()->create(['organization_id' => $org->id, 'role' => MembershipRole::Owner]);
        $this->browserRequest('POST', $this->path($org), ['user_public_id' => $user->public_id, 'role' => 'owner'])->assertForbidden();
        $this->browserRequest('PATCH', $this->path($org, $admin->public_id), ['version' => 1, 'role' => 'owner'])->assertForbidden();
        $this->browserRequest('DELETE', $this->path($org, $owner->public_id), ['version' => 1])->assertForbidden();
        $foreign = MembershipRecord::factory()->create();
        $this->browserRequest('PATCH', $this->path($org, $foreign->public_id), ['version' => 1, 'role' => 'viewer'])->assertNotFound();
        $foreignOrg = OrganizationRecord::query()->findOrFail($foreign->organization_id);
        $this->browserRequest('GET', $this->path($foreignOrg))->assertForbidden();
    }

    public static function invalidUpdates(): array
    {
        return [[[]], [['version' => 1]], [['version' => 0, 'role' => 'viewer']], [['version' => 1, 'role' => 'root']], [['version' => 1, 'status' => 'revoked']], [['version' => 1, 'status' => 'active', 'role' => 'owner']], [['version' => 1, 'role' => null]]];
    }

    #[DataProvider('invalidUpdates')]
    public function test_update_validation_uses_problem_details(array $input): void
    {
        [$owner,$org] = $this->owner();
        $this->browserRequest('PATCH', $this->path($org, $owner->public_id), $input)->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
    }

    public function test_authentication_csrf_origin_and_public_uuid_are_enforced(): void
    {
        $org = OrganizationRecord::factory()->create();
        $this->browserRequest('GET', $this->path($org))->assertUnauthorized();
        $this->browserRequest('GET', '/api/v1/auth/organizations')->assertUnauthorized();
        [$owner,$org] = $this->owner();
        $this->browserRequest('DELETE', $this->path($org, $owner->public_id), ['version' => 1], csrf: false)->assertStatus(419)->assertJsonPath('code', 'csrf_token_mismatch');
        $this->browserRequest('GET', $this->path($org), headers: ['HTTP_ORIGIN' => 'https://evil.example'])->assertForbidden();
        $this->browserRequest('GET', '/api/v1/organizations/'.$org->id.'/members')->assertNotFound();
        $this->browserRequest('GET', '/api/v1/organizations/'.Str::uuid().'/members')->assertNotFound();
    }
}
