<?php

namespace Tests\Integration\Organizations;

use App\Modules\Organizations\Application\Contracts\Persistence\OrganizationRepository;
use App\Modules\Organizations\Domain\Entities\Organization;
use App\Modules\Organizations\Domain\OrganizationStatus;
use App\Modules\Organizations\Domain\ValueObjects\OrganizationDocument;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): OrganizationRepository
    {
        return $this->app->make(OrganizationRepository::class);
    }

    private function newOrganization(?string $publicId = null): Organization
    {
        return Organization::create($publicId ?? (string) Str::uuid(), ' Company ', ' Legal ', new OrganizationDocument('52998224725'), new DateTimeImmutable('2026-11-04T13:00:00.123456Z'));
    }

    public function test_container_binding_and_domain_round_trip_preserve_values(): void
    {
        $new = $this->newOrganization();
        $saved = $this->repository()->create($new);
        $found = $this->repository()->findByPublicId(strtoupper($saved->publicId));
        $this->assertNotNull($saved->id);
        $this->assertSame(1, $saved->version);
        $this->assertEquals($saved, $found);
        $this->assertSame('123456', $found->createdAt->format('u'));
        $this->assertSame('Company', $found->name);
        $this->assertNull($new->id);
        $this->assertDatabaseCount('organizations', 1);
    }

    public function test_invalid_and_missing_public_identifiers_return_null(): void
    {
        $this->assertNull($this->repository()->findByPublicId('not-a-uuid'));
        $this->assertNull($this->repository()->findByPublicId((string) Str::uuid()));
    }

    public function test_updates_preserve_identity_and_creation_and_persist_inactive_state(): void
    {
        $original = $this->repository()->create($this->newOrganization());
        $later = $original->updatedAt->modify('+1 second');
        $updated = $this->repository()->update($original->updateDetails('Changed', null, null, $later)->changeStatus(OrganizationStatus::Inactive, $later));
        $this->assertSame($original->id, $updated->id);
        $this->assertSame($original->publicId, $updated->publicId);
        $this->assertEquals($original->createdAt, $updated->createdAt);
        $this->assertSame(2, $updated->version);
        $this->assertSame(OrganizationStatus::Inactive, $updated->status);
        $this->assertNull($updated->document);
        $this->assertEquals($updated, $this->repository()->findByPublicId($updated->publicId));
    }

    public function test_duplicate_public_id_becomes_safe_conflict_and_transaction_recovers(): void
    {
        $original = $this->repository()->create($this->newOrganization());
        try {
            $this->repository()->create($this->newOrganization($original->publicId));
            $this->fail('Expected a conflict.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame(FailureKind::Conflict, $failure->kind);
            $this->assertSame('organizations.public_id_conflict', $failure->errorCode);
            $this->assertStringNotContainsString($original->publicId, $failure->publicMessage);
        }
        $this->assertDatabaseCount('organizations', 1);
        $this->repository()->create($this->newOrganization());
        $this->assertDatabaseCount('organizations', 2);
    }

    public function test_stale_snapshot_cannot_overwrite_a_newer_change_even_at_same_timestamp(): void
    {
        $original = $this->repository()->create($this->newOrganization());
        $this->repository()->update($original->changeStatus(OrganizationStatus::Inactive, $original->updatedAt));
        try {
            $this->repository()->update($original->updateDetails('Lost update', null, null, $original->updatedAt));
            $this->fail('Expected stale snapshot rejection.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('organizations.concurrent_update', $failure->errorCode);
        }
        $found = $this->repository()->findByPublicId($original->publicId);
        $this->assertSame('Company', $found->name);
        $this->assertSame(OrganizationStatus::Inactive, $found->status);
    }

    public function test_missing_record_cannot_be_silently_recreated_on_update(): void
    {
        $original = $this->repository()->create($this->newOrganization());
        OrganizationRecord::query()->whereKey($original->id)->delete();
        $this->expectException(ApplicationFailure::class);
        $this->expectExceptionMessage('Organization not found.');
        $this->repository()->update($original);
    }

    public function test_unexpected_database_failure_is_not_disguised_as_business_conflict(): void
    {
        $original = $this->repository()->create($this->newOrganization());
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_probe_failure CHECK (name <> 'Rejected')");
        $candidate = $this->newOrganization()->updateDetails('Rejected', null, null, new DateTimeImmutable('2026-11-04T14:00:00Z'));
        try {
            $this->repository()->create($candidate);
            $this->fail('Expected a database constraint failure.');
        } catch (QueryException $failure) {
            $this->assertSame('23514', $failure->errorInfo[0]);
        }
        $this->assertDatabaseCount('organizations', 1);
        $this->assertEquals($original, $this->repository()->findByPublicId($original->publicId));
    }

    public function test_failed_update_rolls_back_and_does_not_increment_version(): void
    {
        $original = $this->repository()->create($this->newOrganization());
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_probe_update CHECK (name <> 'Rejected')");
        try {
            $this->repository()->update($original->updateDetails('Rejected', null, null, $original->updatedAt));
            $this->fail('Expected a database constraint failure.');
        } catch (QueryException $failure) {
            $this->assertSame('23514', $failure->errorInfo[0]);
        }
        $this->assertEquals($original, $this->repository()->findByPublicId($original->publicId));
    }
}
