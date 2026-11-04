<?php

namespace Tests\Support\Security;

use App\Modules\IdentityAccess\Application\UseCases\ManageOrganizationMembers;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Extend with a real module adapter; these assertions never replace its domain tests. */
abstract class TenantIsolationContract extends TestCase
{
    use RefreshDatabase;

    abstract protected function prepareStorage(): void;

    abstract protected function createResource(TenantContext $context, string $value): string;

    abstract protected function readResource(TenantContext $context, string $id): ?string;

    abstract protected function updateResource(TenantContext $context, string $id, string $value): void;

    abstract protected function archiveResource(TenantContext $context, string $id): void;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareStorage();
    }

    private function context(?int $userId = null): TenantContext
    {
        $attributes = [];
        if ($userId !== null) {
            $attributes['user_id'] = $userId;
        }
        $member = MembershipRecord::factory()->create($attributes);
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $user = UserRecord::query()->findOrFail($member->user_id);

        return new TenantContext($org->id, $org->public_id, $user->public_id, $user->version, $member->public_id, $member->version);
    }

    private function notFound(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Foreign resource must not be mutable');
        } catch (ApplicationFailure $failure) {
            $this->assertSame(FailureKind::NotFound, $failure->kind);
        }
    }

    public function test_contract_known_foreign_identifiers_cannot_be_read_or_mutated(): void
    {
        $a = $this->context();
        $b = $this->context();
        $idA = $this->createResource($a, 'Organization A private value');
        $idB = $this->createResource($b, 'Organization B private value');
        $this->assertSame('Organization A private value', $this->readResource($a, $idA));
        $this->assertNull($this->readResource($a, $idB));
        $this->assertNull($this->readResource($b, $idA));
        $this->notFound(fn () => $this->updateResource($a, $idB, 'Leaked'));
        $this->notFound(fn () => $this->archiveResource($a, $idB));
        $this->assertSame('Organization B private value', $this->readResource($b, $idB));
    }

    public function test_contract_sequential_context_switches_do_not_reuse_scope(): void
    {
        $a = $this->context();
        $user = UserRecord::query()->where('public_id', $a->actorPublicId)->firstOrFail();
        $b = $this->context($user->id);
        $idA = $this->createResource($a, 'A');
        $idB = $this->createResource($b, 'B');
        $this->updateResource($b, $idB, 'B updated');
        $this->assertSame('A', $this->readResource($a, $idA));
        $this->updateResource($a, $idA, 'A updated');
        $this->archiveResource($b, $idB);
        $this->assertNull($this->readResource($b, $idB));
        $this->assertSame('A updated', $this->readResource($a, $idA));
    }

    public function test_contract_revocation_prevents_old_context_reads_and_writes(): void
    {
        $context = $this->context();
        $id = $this->createResource($context, 'Preserved');
        $owner = MembershipRecord::factory()->create(['organization_id' => $context->organizationId, 'role' => 'owner']);
        $ownerUser = UserRecord::query()->findOrFail($owner->user_id);
        $ownerContext = new TenantContext($context->organizationId, $context->organizationPublicId, $ownerUser->public_id, $ownerUser->version, $owner->public_id, $owner->version);
        app(ManageOrganizationMembers::class)->revoke($ownerContext, $context->membershipPublicId, $context->membershipVersion);
        foreach ([fn () => $this->readResource($context, $id), fn () => $this->updateResource($context, $id, 'Denied'), fn () => $this->archiveResource($context, $id), fn () => $this->createResource($context, 'Denied')] as $operation) {
            try {
                $operation();
                $this->fail('Revoked context must be denied');
            } catch (ApplicationFailure $failure) {
                $this->assertSame(FailureKind::Forbidden, $failure->kind);
            }
        }
    }
}
