<?php

namespace Tests\Feature;

use App\Modules\IdentityAccess\Domain\UserProfile;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\StatefulBrowser;
use Tests\TestCase;

#[Group('security')]
class UserProfileContractTest extends TestCase
{
    use RefreshDatabase, StatefulBrowser;

    private function login(UserRecord $user): void
    {
        $this->configureBrowser();
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $login = $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => 'testing-only-password']);
        $login->assertOk()->assertJsonPath('data.profile', $user->profile->value);
        $me = $this->browserRequest('GET', '/api/v1/auth/me');
        $me->assertOk()->assertJsonPath('data.profile', $user->profile->value)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($login->json(), $me->json());
        $this->assertSame(['public_id', 'name', 'email', 'profile', 'status', 'email_verified_at'], array_keys($me->json('data')));
        foreach (['password', 'remember_token', 'id', 'version', 'session_id', 'token'] as $field) {
            $this->assertArrayNotHasKey($field, $me->json('data'));
        }
    }

    public static function accessCases(): array
    {
        return [['standard', 0], ['standard', 1], ['consultant', 0], ['consultant', 1], ['consultant', 2]];
    }

    #[DataProvider('accessCases')]
    public function test_identity_and_authorized_organizations_remain_compatible(string $profile, int $count): void
    {
        $user = UserRecord::factory()->create(['profile' => $profile]);
        $organizations = [];
        for ($i = 0; $i < $count; $i++) {
            $membership = MembershipRecord::factory()->create(['user_id' => $user->id, 'role' => $i === 0 ? 'owner' : 'viewer']);
            $organizations[] = $membership->organization_id;
        }
        MembershipRecord::factory()->revoked()->create(['user_id' => $user->id]);
        if ($profile === 'consultant') {
            $inactive = OrganizationRecord::factory()->inactive()->create();
            MembershipRecord::factory()->create(['user_id' => $user->id, 'organization_id' => $inactive->id]);
        }
        $this->login($user);
        $response = $this->browserRequest('GET', '/api/v1/auth/organizations')->assertOk()->assertJsonCount($count, 'data');
        foreach ($response->json('data') as $item) {
            $this->assertSame(['public_id', 'name', 'role', 'membership_public_id'], array_keys($item));
        }
        if ($count === 0) {
            $this->assertSame(['data' => []], $response->json());
            $foreign = OrganizationRecord::factory()->create();
            $this->browserRequest('GET', '/api/v1/organizations/'.$foreign->public_id.'/members')->assertForbidden();
        }
        if ($count === 2) {
            $first = OrganizationRecord::query()->findOrFail($organizations[0]);
            $second = OrganizationRecord::query()->findOrFail($organizations[1]);
            $this->browserRequest('GET', '/api/v1/organizations/'.$first->public_id.'/members')->assertOk();
            $this->browserRequest('GET', '/api/v1/organizations/'.$second->public_id.'/members')->assertForbidden();
            $this->browserRequest('GET', '/api/v1/organizations/'.$first->public_id.'/members')->assertOk();
        }
        $this->browserRequest('POST', '/api/v1/auth/logout')->assertNoContent();
        $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_add_and_reactivation_limits_return_stable_problem_details(): void
    {
        $owner = UserRecord::factory()->create();
        $ownerMembership = MembershipRecord::factory()->create(['user_id' => $owner->id, 'role' => 'owner']);
        $org = OrganizationRecord::query()->findOrFail($ownerMembership->organization_id);
        $target = UserRecord::factory()->create();
        MembershipRecord::factory()->create(['user_id' => $target->id]);
        $this->login($owner);
        $path = '/api/v1/organizations/'.$org->public_id.'/members';
        $failure = $this->browserRequest('POST', $path, ['user_public_id' => $target->public_id, 'role' => 'viewer']);
        $failure->assertConflict()->assertHeader('Content-Type', 'application/problem+json')->assertJsonPath('status', 409)->assertJsonPath('code', 'memberships.active_limit')->assertJsonPath('type', 'urn:bacurim:problem:memberships.active_limit');
        $this->assertArrayNotHasKey('trace', $failure->json());
        $this->assertArrayNotHasKey('password', $failure->json());
        $revoked = MembershipRecord::factory()->revoked()->create(['user_id' => $target->id, 'organization_id' => $org->id]);
        $this->browserRequest('PATCH', $path.'/'.$revoked->public_id, ['version' => 1, 'status' => 'active'])->assertConflict()->assertJsonPath('code', 'memberships.active_limit');
        $this->assertSame('revoked', $revoked->fresh()->status->value);
        $this->assertSame(UserProfile::Standard, $target->fresh()->profile);
    }
}
