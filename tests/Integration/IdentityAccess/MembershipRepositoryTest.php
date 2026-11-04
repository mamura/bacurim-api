<?php

namespace Tests\Integration\IdentityAccess;

use App\Modules\IdentityAccess\Application\Contracts\Persistence\MembershipRepository;
use App\Modules\IdentityAccess\Domain\Entities\OrganizationMembership;
use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Domain\MembershipStatus;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): MembershipRepository
    {
        return $this->app->make(MembershipRepository::class);
    }

    private function newMembership(?int $org = null, ?int $user = null): OrganizationMembership
    {
        return OrganizationMembership::create((string) Str::uuid(), $org ?? OrganizationRecord::factory()->create()->id, $user ?? UserRecord::factory()->create()->id, MembershipRole::Owner, new DateTimeImmutable('2026-11-04T13:00:00.123456Z'), true, true);
    }

    public function test_round_trip_and_queries_preserve_scope_and_microseconds(): void
    {
        $first = $this->repository()->create($this->newMembership());
        $second = $this->repository()->create($this->newMembership(user: $first->userId));
        $this->assertSame(1, $first->version);
        $this->assertSame('123456', $first->createdAt->format('u'));
        $this->assertEquals($first, $this->repository()->findInOrganization($first->organizationId, strtoupper($first->publicId)));
        $this->assertEquals([$first], $this->repository()->forOrganization($first->organizationId));
        $this->assertEquals([$first, $second], $this->repository()->forUser($first->userId));
        $this->assertEquals($first, $this->repository()->findActive($first->organizationId, $first->userId));
        $this->assertNull($this->repository()->findInOrganization($first->organizationId, 'invalid'));
        $this->assertNull($this->repository()->findInOrganization($first->organizationId, $second->publicId));
        $this->assertNull($this->repository()->findForUser($first->organizationId, $second->userId + 10000));
    }

    public function test_revocation_and_reactivation_reuse_the_same_row(): void
    {
        $saved = $this->repository()->create($this->newMembership());
        $revoked = $this->repository()->update($saved->revoke($saved->updatedAt));
        $this->assertSame(2, $revoked->version);
        $this->assertSame(MembershipStatus::Revoked, $revoked->status);
        $this->assertNull($this->repository()->findActive($saved->organizationId, $saved->userId));
        $active = $this->repository()->update($revoked->activate($saved->updatedAt, true, true));
        $this->assertSame($saved->id, $active->id);
        $this->assertSame(3, $active->version);
        $this->assertDatabaseCount('organization_memberships', 1);
    }

    public function test_duplicate_pair_is_rejected_even_after_revocation_and_transaction_recovers(): void
    {
        $saved = $this->repository()->create($this->newMembership());
        $this->repository()->update($saved->revoke($saved->updatedAt));
        try {
            $this->repository()->create($this->newMembership($saved->organizationId, $saved->userId));
            $this->fail('Expected conflict');
        } catch (ApplicationFailure $failure) {
            $this->assertSame(FailureKind::Conflict, $failure->kind);
            $this->assertSame('memberships.duplicate', $failure->errorCode);
        }
        $this->repository()->create($this->newMembership());
        $this->assertDatabaseCount('organization_memberships', 2);
    }

    public function test_stale_update_cannot_overwrite_a_new_role(): void
    {
        $saved = $this->repository()->create($this->newMembership());
        $this->repository()->update($saved->changeRole(MembershipRole::Admin, $saved->updatedAt));
        $this->expectException(ApplicationFailure::class);
        $this->repository()->update($saved->revoke($saved->updatedAt));
    }

    public function test_user_and_organization_cannot_be_reassigned(): void
    {
        $saved = $this->repository()->create($this->newMembership());
        $forged = new OrganizationMembership($saved->id, $saved->publicId, $saved->organizationId, $saved->userId + 1, $saved->role, $saved->status, $saved->createdAt, $saved->updatedAt, null, $saved->version);
        $this->expectException(LogicException::class);
        $this->repository()->update($forged);
    }

    public static function invalidRows(): array
    {
        return [[['role' => 'superadmin']], [['status' => 'inactive']], [['version' => 0]], [['user_id' => 9999999]], [['organization_id' => 9999999]], [['status' => 'revoked', 'revoked_at' => null]], [['revoked_at' => '2026-11-04T12:00:00Z']]];
    }

    #[DataProvider('invalidRows')]
    public function test_database_constraints_reject_invalid_rows(array $changes): void
    {
        $row = MembershipRecord::factory()->create();
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('organization_memberships')->where('id', $row->id)->update($changes));
    }

    public function test_factory_supports_revoked_state_and_foreign_keys_prevent_deletion(): void
    {
        $row = MembershipRecord::factory()->revoked()->create();
        $this->assertSame(MembershipStatus::Revoked, $row->status);
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('users')->where('id', $row->user_id)->delete());
    }
}
