<?php

namespace Tests\Integration\Tenancy;

use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantAuthorization;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantCapability;
use App\Modules\IdentityAccess\Application\UseCases\ManageOrganizationMembers;
use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MembershipTestActor;
use Tests\TestCase;

class TenantApplicationTest extends TestCase
{
    use MembershipTestActor,RefreshDatabase;

    private function fixture(): array
    {
        $member = MembershipRecord::factory()->create(['role' => MembershipRole::Owner]);
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $this->useMembershipActor(UserRecord::query()->findOrFail($member->user_id));

        return [$member, $org, $this->context($org)];
    }

    private function denied(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected denial');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('tenant.forbidden', $failure->errorCode);
        }
    }

    public function test_explicit_context_is_required_and_unrelated_internal_organization_id_is_denied(): void
    {
        [$member,$org,$context] = $this->fixture();
        $other = OrganizationRecord::factory()->create();
        $forged = new TenantContext($other->id, $context->organizationPublicId, $context->actorPublicId, $context->actorVersion, $context->membershipPublicId, $context->membershipVersion);
        $this->denied(fn () => app(ManageOrganizationMembers::class)->list($forged));
        $this->assertCount(1, app(ManageOrganizationMembers::class)->list($context));
    }

    public function test_revocation_role_change_and_user_change_invalidate_previously_resolved_context(): void
    {
        [$member,$org,$context] = $this->fixture();
        $member->forceFill(['role' => 'viewer', 'version' => 2])->save();
        $this->denied(fn () => app(TenantAuthorization::class)->authorize($context, TenantCapability::ReadFinancial));
        $fresh = $this->context($org);
        app(TenantAuthorization::class)->authorize($fresh, TenantCapability::ReadFinancial);
        $this->denied(fn () => app(TenantAuthorization::class)->authorize($fresh, TenantCapability::WriteFinancial));
        $member->forceFill(['status' => 'revoked', 'revoked_at' => $member->updated_at, 'version' => 3])->save();
        $this->denied(fn () => app(TenantAuthorization::class)->authorize($fresh, TenantCapability::ReadFinancial));
        $member->forceFill(['status' => 'active', 'revoked_at' => null, 'version' => 4])->save();
        $current = $this->context($org);
        UserRecord::query()->whereKey($member->user_id)->update(['version' => 2]);
        $this->denied(fn () => app(TenantAuthorization::class)->authorize($current, TenantCapability::ReadFinancial));
    }

    public function test_operation_rechecks_inactive_organization_even_after_context_resolution(): void
    {
        [$member,$org,$context] = $this->fixture();
        $org->forceFill(['status' => 'inactive'])->save();
        $this->denied(fn () => app(ManageOrganizationMembers::class)->list($context));
    }

    public function test_context_cannot_substitute_another_membership_snapshot(): void
    {
        [$member,$org,$context] = $this->fixture();
        $other = MembershipRecord::factory()->create(['organization_id' => $org->id]);
        $forged = new TenantContext($context->organizationId, $context->organizationPublicId, $context->actorPublicId, 1, $other->public_id, 1);
        $this->denied(fn () => app(TenantAuthorization::class)->authorize($forged, TenantCapability::ReadFinancial));
    }
}
