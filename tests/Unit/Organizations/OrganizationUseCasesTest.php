<?php

namespace Tests\Unit\Organizations;

use App\Modules\Organizations\Application\Contracts\OrganizationClock;
use App\Modules\Organizations\Application\Contracts\OrganizationIdGenerator;
use App\Modules\Organizations\Application\Contracts\Persistence\OrganizationRepository;
use App\Modules\Organizations\Application\Data\OrganizationDetails;
use App\Modules\Organizations\Application\OrganizationLookup;
use App\Modules\Organizations\Application\UseCases\ChangeOrganizationStatus;
use App\Modules\Organizations\Application\UseCases\CreateOrganization;
use App\Modules\Organizations\Application\UseCases\GetOrganization;
use App\Modules\Organizations\Application\UseCases\UpdateOrganization;
use App\Modules\Organizations\Domain\Entities\Organization;
use App\Modules\Organizations\Domain\OrganizationStatus;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class OrganizationUseCasesTest extends TestCase
{
    private const UUID = 'c4ca63e2-92a4-4c17-bf34-c08753927800';

    private function clock(): OrganizationClock
    {
        return new class implements OrganizationClock
        {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-04T13:00:00Z');
            }
        };
    }

    private function existing(): Organization
    {
        $time = new DateTimeImmutable('2026-11-04T12:00:00Z');

        return new Organization(42, self::UUID, 'Original', null, null, OrganizationStatus::Active, $time, $time, 1);
    }

    public function test_creation_passes_normalized_domain_data_to_its_port_without_laravel(): void
    {
        $repository = $this->createMock(OrganizationRepository::class);
        $repository->expects($this->once())->method('create')->willReturnCallback(function (Organization $entity): Organization {
            $this->assertNull($entity->id);
            $this->assertSame(self::UUID, $entity->publicId);
            $this->assertSame('Company', $entity->name);
            $this->assertSame('52998224725', $entity->document->value);
            $this->assertEquals($this->clock()->now(), $entity->createdAt);

            return new Organization(42, $entity->publicId, $entity->name, $entity->legalName, $entity->document, $entity->status, $entity->createdAt, $entity->updatedAt, 1);
        });
        $ids = new class implements OrganizationIdGenerator
        {
            public function generate(): string
            {
                return 'c4ca63e2-92a4-4c17-bf34-c08753927800';
            }
        };
        $data = (new CreateOrganization($repository, $this->clock(), $ids))->execute(new OrganizationDetails(' Company ', null, '529.982.247-25'));
        $this->assertSame(1, $data->version);
        $this->assertFalse(property_exists($data, 'id'));
    }

    public function test_invalid_creation_never_calls_persistence(): void
    {
        $repository = $this->createMock(OrganizationRepository::class);
        $repository->expects($this->never())->method('create');
        $ids = $this->createMock(OrganizationIdGenerator::class);
        $ids->method('generate')->willReturn(self::UUID);
        $this->expectException(ApplicationFailure::class);
        (new CreateOrganization($repository, $this->clock(), $ids))->execute(new OrganizationDetails(''));
    }

    public function test_query_translates_absence_to_not_found(): void
    {
        $repository = $this->createMock(OrganizationRepository::class);
        $repository->method('findByPublicId')->willReturn(null);
        try {
            (new GetOrganization(new OrganizationLookup($repository)))->execute(self::UUID);
            $this->fail('Expected missing organization.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame(FailureKind::NotFound, $failure->kind);
        }
    }

    public function test_update_preserves_identity_and_uses_the_injected_clock(): void
    {
        $repository = $this->createMock(OrganizationRepository::class);
        $original = $this->existing();
        $repository->method('findByPublicId')->willReturn($original);
        $repository->expects($this->once())->method('update')->willReturnCallback(function (Organization $entity) use ($original): Organization {
            $this->assertSame($original->id, $entity->id);
            $this->assertSame($original->publicId, $entity->publicId);
            $this->assertEquals($original->createdAt, $entity->createdAt);
            $this->assertEquals($this->clock()->now(), $entity->updatedAt);
            $this->assertSame('Changed', $entity->name);

            return $entity;
        });
        (new UpdateOrganization($repository, new OrganizationLookup($repository), $this->clock()))->execute(self::UUID, new OrganizationDetails('Changed'), 1);
    }

    public function test_stale_status_change_never_reaches_the_write_port(): void
    {
        $repository = $this->createMock(OrganizationRepository::class);
        $repository->method('findByPublicId')->willReturn($this->existing());
        $repository->expects($this->never())->method('update');
        try {
            (new ChangeOrganizationStatus($repository, new OrganizationLookup($repository), $this->clock()))->execute(self::UUID, OrganizationStatus::Inactive, 0);
            $this->fail('Expected conflict.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame(FailureKind::Conflict, $failure->kind);
        }
    }

    public function test_missing_update_never_creates_a_new_organization(): void
    {
        $repository = $this->createMock(OrganizationRepository::class);
        $repository->method('findByPublicId')->willReturn(null);
        $repository->expects($this->never())->method('update');
        $repository->expects($this->never())->method('create');
        $this->expectException(ApplicationFailure::class);
        (new UpdateOrganization($repository, new OrganizationLookup($repository), $this->clock()))->execute(self::UUID, new OrganizationDetails('Changed'), 1);
    }
}
