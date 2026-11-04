<?php

namespace Tests\Feature;

use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\IdentityAccess\Interfaces\Http\TenantContextReader;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\StatefulBrowser;
use Tests\Support\Tenancy\DowngradeTenantActor;
use Tests\Support\Tenancy\ProbeResource;
use Tests\Support\Tenancy\ProbeSchema;
use Tests\Support\Tenancy\ProbeUseCases;
use Tests\Support\Tenancy\ScopedProbeRepository;
use Tests\TestCase;

class TenantIsolationApiTest extends TestCase
{
    use RefreshDatabase,StatefulBrowser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureBrowser();
        ProbeSchema::create();
        Route::prefix('api/v1/organizations/{organization}/_isolation')->middleware(['api', 'auth:sanctum', 'auth.session.valid', 'tenant.context'])->group(function (): void {
            Route::get('{record}', fn (string $organization, string $record, TenantContextReader $reader, ProbeUseCases $cases) => new ProbeResource($cases->read($reader->get(), $record)));
            Route::post('', function (Request $request, string $organization, TenantContextReader $reader, ProbeUseCases $cases) {
                $input = $request->validate(['label' => ['required', 'string', 'max:255'], 'organization_id' => ['prohibited'], 'organization_public_id' => ['prohibited']]);

                return (new ProbeResource($cases->create($reader->get(), $input['label'])))->response()->setStatusCode(201);
            });
            Route::patch('{record}', function (Request $request, string $organization, string $record, TenantContextReader $reader, ProbeUseCases $cases) {
                $input = $request->validate(['label' => ['required', 'string'], 'organization_id' => ['prohibited'], 'organization_public_id' => ['prohibited']]);

                return new ProbeResource($cases->rename($reader->get(), $record, $input['label']));
            });
            Route::delete('{record}', function (string $organization, string $record, TenantContextReader $reader, ProbeUseCases $cases) {
                $cases->archive($reader->get(), $record);

                return response()->noContent();
            });
        });
    }

    private function fixture(MembershipRole $role = MembershipRole::Member, ?int $userId = null): array
    {
        $attributes = ['role' => $role];
        if ($userId !== null) {
            $attributes['user_id'] = $userId;
        }
        $member = MembershipRecord::factory()->create($attributes);
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $user = UserRecord::query()->findOrFail($member->user_id);
        $context = new TenantContext($org->id, $org->public_id, $user->public_id, 1, $member->public_id, 1);

        return [$member, $org, $user, $context];
    }

    private function login(UserRecord $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => 'testing-only-password'])->assertOk();
    }

    private function path(OrganizationRecord $org, ?string $record = null): string
    {
        return '/api/v1/organizations/'.$org->public_id.'/_isolation'.($record === null ? '' : '/'.$record);
    }

    public static function roles(): array
    {
        return [[MembershipRole::Owner, true], [MembershipRole::Admin, true], [MembershipRole::Member, true], [MembershipRole::Viewer, false]];
    }

    #[DataProvider('roles')]
    public function test_role_permissions_apply_to_scoped_reads_and_writes(MembershipRole $role, bool $write): void
    {
        [$member,$org,$user,$context] = $this->fixture($role);
        $id = (new ScopedProbeRepository)->create($context, 'Original');
        $this->login($user);
        $this->browserRequest('GET', $this->path($org, $id))->assertOk()->assertJsonPath('data.label', 'Original');
        $this->browserRequest('POST', $this->path($org), ['label' => 'Created'])->assertStatus($write ? 201 : 403);
        $this->browserRequest('PATCH', $this->path($org, $id), ['label' => 'Updated'])->assertStatus($write ? 200 : 403);
        $this->browserRequest('DELETE', $this->path($org, $id))->assertStatus($write ? 204 : 403);
        if (! $write) {
            $this->assertSame('Original', (new ScopedProbeRepository)->find($context, $id)->label);
        }
        $this->assertFalse(app('request')->attributes->has(TenantContext::class));
        $this->assertFalse(app()->bound(TenantContext::class));
    }

    public function test_known_resource_uuid_cannot_be_read_modified_or_archived_from_another_organization(): void
    {
        [$a,$orgA,$userA,$contextA] = $this->fixture();
        [$b,$orgB,$userB,$contextB] = $this->fixture();
        $id = (new ScopedProbeRepository)->create($contextB, 'Private B');
        $this->login($userA);
        $this->browserRequest('GET', $this->path($orgA, 'invalid'))->assertNotFound();
        $this->browserRequest('GET', $this->path($orgA, $id))->assertNotFound()->assertJsonPath('code', 'probe.not_found');
        $this->browserRequest('PATCH', $this->path($orgA, $id), ['label' => 'Leaked'])->assertNotFound();
        $this->browserRequest('DELETE', $this->path($orgA, $id))->assertNotFound();
        $this->browserRequest('GET', $this->path($orgB, $id))->assertForbidden()->assertJsonPath('code', 'tenant.forbidden');
        $this->assertSame('Private B', (new ScopedProbeRepository)->find($contextB, $id)->label);
    }

    public function test_creation_and_update_cannot_choose_or_transfer_organizational_ownership(): void
    {
        [$a,$orgA,$userA,$contextA] = $this->fixture();
        [$b,$orgB,$userB,$contextB] = $this->fixture();
        $id = (new ScopedProbeRepository)->create($contextA, 'A');
        $this->login($userA);
        $this->browserRequest('POST', $this->path($orgA), ['label' => 'Forged', 'organization_id' => $orgB->id])->assertUnprocessable();
        $this->browserRequest('PATCH', $this->path($orgA, $id), ['label' => 'Forged', 'organization_public_id' => $orgB->public_id])->assertUnprocessable();
        $created = $this->browserRequest('POST', $this->path($orgA), ['label' => 'Scoped'], ['HTTP_X_ORGANIZATION_ID' => $orgB->public_id]);
        $created->assertCreated();
        $this->assertDatabaseHas('tenant_isolation_probes', ['public_id' => $created->json('data.public_id'), 'organization_id' => $orgA->id]);
        $this->assertSame([], (new ScopedProbeRepository)->labels($contextB));
    }

    public function test_switching_between_owner_and_viewer_organizations_does_not_reuse_permissions(): void
    {
        [$a,$orgA,$user,$contextA] = $this->fixture(MembershipRole::Owner);
        [$b,$orgB,$same,$contextB] = $this->fixture(MembershipRole::Viewer, $user->id);
        $repo = new ScopedProbeRepository;
        $idA = $repo->create($contextA, 'A');
        $idB = $repo->create($contextB, 'B');
        $this->login($user);
        $this->browserRequest('GET', $this->path($orgA, $idA))->assertOk()->assertJsonPath('data.label', 'A');
        $this->browserRequest('GET', $this->path($orgB, $idB))->assertOk()->assertJsonPath('data.label', 'B');
        $this->browserRequest('PATCH', $this->path($orgB, $idB), ['label' => 'Denied'])->assertForbidden();
        $this->browserRequest('PATCH', $this->path($orgA, $idA), ['label' => 'Allowed'])->assertOk();
        $this->browserRequest('GET', '/api/v1/auth/me')->assertOk();
        $this->assertFalse(app('request')->attributes->has(TenantContext::class));
    }

    public function test_revoked_membership_blocks_next_request_without_poisoning_other_tenant(): void
    {
        [$a,$orgA,$user,$contextA] = $this->fixture();
        [$b,$orgB,$same,$contextB] = $this->fixture(userId: $user->id);
        $repo = new ScopedProbeRepository;
        $idA = $repo->create($contextA, 'A');
        $idB = $repo->create($contextB, 'B');
        $this->login($user);
        $a->forceFill(['status' => 'revoked', 'revoked_at' => $a->updated_at, 'version' => 2])->save();
        $this->browserRequest('GET', $this->path($orgA, $idA))->assertForbidden();
        $this->browserRequest('GET', $this->path($orgB, $idB))->assertOk();
        $this->browserRequest('GET', '/api/v1/auth/organizations')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.public_id', $orgB->public_id);
    }

    public function test_context_is_revalidated_if_permission_changes_after_middleware(): void
    {
        [$member,$org,$user,$context] = $this->fixture();
        Route::post('api/v1/organizations/{organization}/_isolation-stale', fn (string $organization, TenantContextReader $reader, ProbeUseCases $cases) => new ProbeResource($cases->create($reader->get(), 'Denied')))->middleware(['api', 'auth:sanctum', 'auth.session.valid', 'tenant.context', DowngradeTenantActor::class]);
        $this->login($user);
        $this->browserRequest('POST', '/api/v1/organizations/'.$org->public_id.'/_isolation-stale')->assertForbidden()->assertJsonPath('code', 'tenant.forbidden');
        $this->assertDatabaseCount('tenant_isolation_probes', 0);
        $this->assertFalse(app('request')->attributes->has(TenantContext::class));
    }
}
