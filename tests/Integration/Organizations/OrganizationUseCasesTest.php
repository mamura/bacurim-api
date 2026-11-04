<?php

namespace Tests\Integration\Organizations;

use App\Modules\Organizations\Application\Data\OrganizationDetails;
use App\Modules\Organizations\Application\UseCases\ChangeOrganizationStatus;
use App\Modules\Organizations\Application\UseCases\CreateOrganization;
use App\Modules\Organizations\Application\UseCases\GetOrganization;
use App\Modules\Organizations\Application\UseCases\UpdateOrganization;
use App\Modules\Organizations\Domain\OrganizationStatus;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationUseCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_lifecycle_resolves_from_container_and_returns_data(): void
    {
        $created = $this->app->make(CreateOrganization::class)->execute(new OrganizationDetails(' Company ', ' Legal ', '529.982.247-25'));
        $this->assertSame('Company', $created->name);
        $this->assertSame(1, $created->version);
        $this->assertSame('52998224725', $created->document);
        $this->assertEquals($created, $this->app->make(GetOrganization::class)->execute($created->publicId));
        $updated = $this->app->make(UpdateOrganization::class)->execute($created->publicId, new OrganizationDetails('Changed'), $created->version);
        $inactive = $this->app->make(ChangeOrganizationStatus::class)->execute($created->publicId, OrganizationStatus::Inactive, $updated->version);
        $active = $this->app->make(ChangeOrganizationStatus::class)->execute($created->publicId, OrganizationStatus::Active, $inactive->version);
        $this->assertSame('Changed', $active->name);
        $this->assertNull($active->legalName);
        $this->assertNull($active->document);
        $this->assertSame(OrganizationStatus::Inactive, $inactive->status);
        $this->assertSame(OrganizationStatus::Active, $active->status);
        $this->assertEquals($created->createdAt, $active->createdAt);
        $this->assertSame(4, $active->version);
        $this->assertDatabaseCount('organizations', 1);
    }

    public function test_invalid_input_is_a_validation_failure_and_does_not_write(): void
    {
        try {
            $this->app->make(CreateOrganization::class)->execute(new OrganizationDetails(''));
            $this->fail('Expected validation failure.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame(FailureKind::Validation, $failure->kind);
            $this->assertSame('organizations.invalid', $failure->errorCode);
        }
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_unknown_organization_is_a_safe_not_found(): void
    {
        $this->expectException(ApplicationFailure::class);
        $this->expectExceptionMessage('Organization not found.');
        $this->app->make(GetOrganization::class)->execute('not-a-uuid');
    }

    public function test_client_version_is_checked_before_an_update(): void
    {
        $created = $this->app->make(CreateOrganization::class)->execute(new OrganizationDetails('Original'));
        $this->app->make(UpdateOrganization::class)->execute($created->publicId, new OrganizationDetails('First'), 1);
        try {
            $this->app->make(UpdateOrganization::class)->execute($created->publicId, new OrganizationDetails('Second'), 1);
            $this->fail('Expected conflict.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame(FailureKind::Conflict, $failure->kind);
        }
        $this->assertSame('First', $this->app->make(GetOrganization::class)->execute($created->publicId)->name);
    }

    public function test_invalid_update_does_not_change_persisted_details(): void
    {
        $created = $this->app->make(CreateOrganization::class)->execute(new OrganizationDetails('Original'));
        try {
            $this->app->make(UpdateOrganization::class)->execute($created->publicId, new OrganizationDetails('Changed', null, 'bad-document'), 1);
            $this->fail('Expected validation failure.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame(FailureKind::Validation, $failure->kind);
        }
        $this->assertEquals($created, $this->app->make(GetOrganization::class)->execute($created->publicId));
    }
}
