<?php

namespace Tests\Feature;

use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\IdentityAccess\Interfaces\Http\TenantContextReader;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\StatefulBrowser;
use Tests\TestCase;

#[Group('security')]
class TenantResolutionTest extends TestCase
{
    use RefreshDatabase,StatefulBrowser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureBrowser();
        Route::get('api/v1/organizations/{organization}/_tenant-probe', function (Request $request, TenantContextReader $reader) {
            $context = $reader->get();
            if ($request->query('fail')) {
                throw new ApplicationFailure(FailureKind::Conflict, 'probe.failure', 'Simulated operation failure.');
            }

            return response()->json(['data' => ['organization' => $context->organizationPublicId, 'actor' => $context->actorPublicId, 'membership' => $context->membershipPublicId]]);
        })->middleware(['api', 'auth:sanctum', 'auth.session.valid', 'tenant.context']);
        Route::get('api/v1/auth/_tenant-probe', fn (Request $request) => response()->json(['data' => ['has_context' => $request->attributes->has(TenantContext::class)]]))->middleware(['api', 'auth:sanctum', 'auth.session.valid']);
        Route::get('api/v1/auth/_tenant-missing', fn () => response()->noContent())->middleware(['api', 'auth:sanctum', 'auth.session.valid', 'tenant.context']);
    }

    private function login(UserRecord $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => 'testing-only-password'])->assertOk();
    }

    private function path(OrganizationRecord $org): string
    {
        return '/api/v1/organizations/'.$org->public_id.'/_tenant-probe';
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], MembershipRole::cases());
    }

    #[DataProvider('roles')]
    public function test_all_roles_enter_their_own_context_without_granting_extra_permissions(MembershipRole $role): void
    {
        $member = MembershipRecord::factory()->create(['role' => $role]);
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $user = UserRecord::query()->findOrFail($member->user_id);
        $this->login($user);
        $this->browserRequest('GET', $this->path($org))->assertOk()->assertJsonPath('data.organization', $org->public_id)->assertJsonPath('data.actor', $user->public_id)->assertJsonPath('data.membership', $member->public_id);
        $this->assertFalse(app('request')->attributes->has(TenantContext::class));
        $this->browserRequest('GET', '/api/v1/organizations/'.$org->public_id.'/members')->assertStatus(in_array($role, [MembershipRole::Owner, MembershipRole::Admin], true) ? 200 : 403);
    }

    public function test_context_changes_between_requests_and_is_cleared_on_success_failure_and_global_routes(): void
    {
        $first = MembershipRecord::factory()->create();
        $second = MembershipRecord::factory()->create(['user_id' => $first->user_id]);
        $a = OrganizationRecord::query()->findOrFail($first->organization_id);
        $b = OrganizationRecord::query()->findOrFail($second->organization_id);
        $this->login(UserRecord::query()->findOrFail($first->user_id));
        $this->browserRequest('GET', $this->path($a), headers: ['HTTP_X_ORGANIZATION_ID' => $b->public_id])->assertOk()->assertJsonPath('data.organization', $a->public_id);
        $this->browserRequest('GET', $this->path($b))->assertOk()->assertJsonPath('data.organization', $b->public_id);
        $this->browserRequest('GET', $this->path($a).'?fail=1')->assertConflict();
        $this->assertFalse(app('request')->attributes->has(TenantContext::class));
        $this->browserRequest('GET', '/api/v1/auth/_tenant-probe')->assertOk()->assertJsonPath('data.has_context', false);
        $this->browserRequest('GET', '/api/v1/auth/_tenant-missing')->assertUnprocessable()->assertJsonPath('code', 'tenant.organization_required');
    }

    public function test_unassociated_revoked_inactive_and_unknown_organizations_are_denied(): void
    {
        $member = MembershipRecord::factory()->create();
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $other = OrganizationRecord::factory()->create();
        $this->browserRequest('GET', $this->path($org))->assertUnauthorized();
        $this->login(UserRecord::query()->findOrFail($member->user_id));
        $this->browserRequest('GET', $this->path($other))->assertForbidden()->assertJsonPath('code', 'tenant.forbidden');
        $org->forceFill(['status' => 'inactive'])->save();
        $this->browserRequest('GET', $this->path($org))->assertForbidden();
        $org->forceFill(['status' => 'active'])->save();
        $member->forceFill(['status' => 'revoked', 'revoked_at' => $member->updated_at])->save();
        $this->browserRequest('GET', $this->path($org))->assertForbidden();
        $this->browserRequest('GET', '/api/v1/organizations/'.Str::uuid().'/_tenant-probe')->assertNotFound()->assertJsonPath('code', 'tenant.not_found');
        $this->browserRequest('GET', '/api/v1/organizations/1/_tenant-probe')->assertNotFound();
        $this->assertFalse(app('request')->attributes->has(TenantContext::class));
    }

    public function test_reader_fails_closed_outside_resolved_request(): void
    {
        $this->expectException(ApplicationFailure::class);
        app(TenantContextReader::class)->get();
    }
}
