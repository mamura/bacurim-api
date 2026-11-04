<?php

namespace Tests\Integration\Tenancy;

use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Tenancy\TenantContext;
use ArgumentCountError;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Tenancy\ProbeSchema;
use Tests\Support\Tenancy\ScopedProbeRepository;
use Tests\TestCase;
use TypeError;

#[Group('security')]
class ScopedPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ProbeSchema::create();
    }

    private function context(): TenantContext
    {
        $member = MembershipRecord::factory()->create();
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $user = UserRecord::query()->findOrFail($member->user_id);

        return new TenantContext($org->id, $org->public_id, $user->public_id, 1, $member->public_id, 1);
    }

    public function test_read_create_update_and_logical_delete_are_explicitly_scoped(): void
    {
        $a = $this->context();
        $b = $this->context();
        $repo = new ScopedProbeRepository;
        $idA = $repo->create($a, 'A');
        $idB = $repo->create($b, 'B');
        $this->assertSame(['A'], $repo->labels($a));
        $this->assertSame(['B'], $repo->labels($b));
        $this->assertNull($repo->find($a, $idB));
        foreach ([fn () => $repo->rename($a, $idB, 'Leaked'), fn () => $repo->archive($a, $idB)] as $operation) {
            try {
                $operation();
                $this->fail('Expected not found');
            } catch (ApplicationFailure $failure) {
                $this->assertSame('probe.not_found', $failure->errorCode);
            }
        }
        $repo->rename($a, $idA, 'Updated A');
        $this->assertSame('Updated A', $repo->find($a, $idA)->label);
        $repo->archive($a, $idA);
        $this->assertNull($repo->find($a, $idA));
        $this->assertSame('B', $repo->find($b, $idB)->label);
        $this->assertDatabaseHas('tenant_isolation_probes', ['public_id' => $idA, 'organization_id' => $a->organizationId, 'label' => 'Updated A']);
        $this->assertNotNull(DB::table('tenant_isolation_probes')->where('public_id', $idA)->value('deleted_at'));
    }

    public function test_missing_or_null_context_cannot_execute_query(): void
    {
        $repo = new ScopedProbeRepository;
        try {
            $repo->labels();
            $this->fail('Expected required argument');
        } catch (ArgumentCountError) {
            $this->assertDatabaseCount('tenant_isolation_probes', 0);
        }
        $this->expectException(TypeError::class);
        $repo->labels(null);
    }

    public function test_create_does_not_accept_owner_and_update_cannot_move_organization(): void
    {
        $context = $this->context();
        $repo = new ScopedProbeRepository;
        $id = $repo->create($context, 'Original');
        $repo->rename($context, $id, 'Renamed');
        $this->assertDatabaseHas('tenant_isolation_probes', ['public_id' => $id, 'organization_id' => $context->organizationId, 'label' => 'Renamed']);
        $this->assertSame(['context', 'label', 'parentPublicId'], array_map(fn ($p) => $p->getName(), (new \ReflectionMethod($repo, 'create'))->getParameters()));
        $this->assertSame(['context', 'publicId', 'label'], array_map(fn ($p) => $p->getName(), (new \ReflectionMethod($repo, 'rename'))->getParameters()));
    }

    public function test_cross_organization_reference_is_rejected_by_lookup(): void
    {
        $a = $this->context();
        $b = $this->context();
        $repo = new ScopedProbeRepository;
        $parent = $repo->create($b, 'Parent');
        $this->expectException(ApplicationFailure::class);
        $repo->create($a, 'Invalid child', $parent);
    }

    public function test_composite_foreign_key_also_blocks_cross_organization_reference_in_postgresql(): void
    {
        $a = $this->context();
        $b = $this->context();
        $repo = new ScopedProbeRepository;
        $parent = $repo->create($b, 'Parent');
        $child = $repo->create($a, 'Child');
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('tenant_isolation_probes')->where('public_id', $child)->update(['parent_public_id' => $parent]));
    }

    public function test_database_requires_an_organization_and_protects_its_foreign_key(): void
    {
        $context = $this->context();
        (new ScopedProbeRepository)->create($context, 'Required FK');
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('organizations')->where('id', $context->organizationId)->delete());
    }

    public function test_postgresql_rejects_missing_organizational_owner(): void
    {
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('tenant_isolation_probes')->insert(['public_id' => (string) Str::uuid(), 'label' => 'Invalid', 'organization_id' => null]));
    }
}
