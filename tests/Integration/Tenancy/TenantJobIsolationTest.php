<?php

namespace Tests\Integration\Tenancy;

use App\Modules\IdentityAccess\Application\Contracts\MembershipActor;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantCapability;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantJobIdentity;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantJobRunner;
use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\MembershipTestActor;
use Tests\Support\Tenancy\ProbeSchema;
use Tests\Support\Tenancy\ScopedProbeRepository;
use Tests\Support\Tenancy\TenantProbeJob;
use Tests\TestCase;

class TenantJobIsolationTest extends TestCase
{
    use MembershipTestActor,RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ProbeSchema::create();
        app()->bind(MembershipActor::class, static fn () => throw new RuntimeException('Job composition must not resolve HTTP identity.'));
    }

    private function fixture(): array
    {
        $member = MembershipRecord::factory()->create(['role' => MembershipRole::Member]);
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $user = UserRecord::query()->findOrFail($member->user_id);
        $context = new TenantContext($org->id, $org->public_id, $user->public_id, 1, $member->public_id, 1);

        return [$member, $org, $context, TenantJobIdentity::fromContext($context)];
    }

    private function dispatch(TenantProbeJob $job): void
    {
        app(Dispatcher::class)->dispatchSync(unserialize(serialize($job)));
    }

    public function test_serialized_jobs_from_two_organizations_remain_isolated_in_same_process(): void
    {
        [$a,$orgA,$contextA,$identityA] = $this->fixture();
        [$b,$orgB,$contextB,$identityB] = $this->fixture();
        $this->dispatch(new TenantProbeJob($identityA, 'A'));
        $this->dispatch(new TenantProbeJob($identityB, 'B'));
        $this->dispatch(new TenantProbeJob($identityA, 'A again'));
        $repo = new ScopedProbeRepository;
        $this->assertEqualsCanonicalizing(['A', 'A again'], $repo->labels($contextA));
        $this->assertSame(['B'], $repo->labels($contextB));
        $this->assertSame(['organization_public_id' => $orgA->public_id, 'actor_public_id' => $contextA->actorPublicId], $identityA->jsonSerialize());
        $this->assertFalse(app('request')->attributes->has(TenantContext::class));
    }

    public function test_membership_is_revalidated_after_dispatch_and_revocation_prevents_work(): void
    {
        [$member,$org,$context,$identity] = $this->fixture();
        $job = new TenantProbeJob($identity, 'Denied');
        $member->forceFill(['status' => 'revoked', 'revoked_at' => $member->updated_at, 'version' => 2])->save();
        try {
            $this->dispatch($job);
            $this->fail('Expected revoked membership denial');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('tenant.forbidden', $failure->errorCode);
        }
        $this->assertDatabaseCount('tenant_isolation_probes', 0);
    }

    public function test_role_downgrade_prevents_previously_queued_write(): void
    {
        [$member,$org,$context,$identity] = $this->fixture();
        $member->forceFill(['role' => MembershipRole::Viewer, 'version' => 2])->save();
        $this->expectException(ApplicationFailure::class);
        $this->dispatch(new TenantProbeJob($identity, 'Denied'));
    }

    public function test_inactive_or_missing_organization_prevents_job_execution(): void
    {
        [$member,$org,$context,$identity] = $this->fixture();
        $org->forceFill(['status' => 'inactive'])->save();
        try {
            $this->dispatch(new TenantProbeJob($identity, 'Denied'));
            $this->fail('Expected inactive denial');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('tenant.forbidden', $failure->errorCode);
        }
        try {
            $this->dispatch(new TenantProbeJob(new TenantJobIdentity((string) Str::uuid(), $context->actorPublicId), 'Missing'));
            $this->fail('Expected missing organization');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('organizations.not_found', $failure->errorCode);
        }
        $this->assertDatabaseCount('tenant_isolation_probes', 0);
    }

    public function test_failed_job_rolls_back_and_same_runner_can_process_another_organization(): void
    {
        [$member,$org,$context,$identity] = $this->fixture();
        [$other,$otherOrg,$otherContext,$otherIdentity] = $this->fixture();
        $runner = app(TenantJobRunner::class);
        try {
            $runner->run($identity, TenantCapability::WriteFinancial, function (TenantContext $scope): void {
                (new ScopedProbeRepository)->create($scope, 'Rollback');
                throw new RuntimeException('Simulated failure');
            });
            $this->fail('Expected operation failure');
        } catch (RuntimeException $failure) {
            $this->assertSame('Simulated failure', $failure->getMessage());
        }
        $runner->run($otherIdentity, TenantCapability::WriteFinancial, fn (TenantContext $scope) => (new ScopedProbeRepository)->create($scope, 'Success'));
        $this->assertSame([], (new ScopedProbeRepository)->labels($context));
        $this->assertSame(['Success'], (new ScopedProbeRepository)->labels($otherContext));
    }

    public function test_jobs_cannot_omit_actor_or_use_arbitrary_identifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TenantJobIdentity((string) Str::uuid(), '');
    }

    public function test_inactive_actor_prevents_queued_operation_before_any_effect(): void
    {
        [$member,$org,$context,$identity] = $this->fixture();
        UserRecord::query()->whereKey($member->user_id)->update(['status' => 'inactive', 'version' => 2]);
        try {
            $this->dispatch(new TenantProbeJob($identity, 'Denied'));
            $this->fail('Expected inactive actor denial');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('tenant.unauthenticated', $failure->errorCode);
        }
        $this->assertDatabaseCount('tenant_isolation_probes', 0);
    }
}
