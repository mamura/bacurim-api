<?php

namespace Tests\Unit\Organizations;

use App\Modules\Organizations\Domain\Entities\Organization;
use App\Modules\Organizations\Domain\Exceptions\InvalidOrganization;
use App\Modules\Organizations\Domain\OrganizationStatus;
use App\Modules\Organizations\Domain\ValueObjects\OrganizationDocument;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrganizationTest extends TestCase
{
    private const UUID = 'c4ca63e2-92a4-4c17-bf34-c08753927800';

    public function test_creation_normalizes_fields_and_starts_active(): void
    {
        $time = new DateTimeImmutable('2026-11-04T12:00:00Z');
        $entity = Organization::create(strtoupper(self::UUID), ' Bacurim ', '  ', null, $time);
        $this->assertNull($entity->id);
        $this->assertSame(self::UUID, $entity->publicId);
        $this->assertSame('Bacurim', $entity->name);
        $this->assertNull($entity->legalName);
        $this->assertNull($entity->document);
        $this->assertSame(OrganizationStatus::Active, $entity->status);
        $this->assertEquals($entity->createdAt, $entity->updatedAt);
    }

    public function test_updates_and_state_transitions_preserve_identity(): void
    {
        $time = new DateTimeImmutable('2026-11-04T12:00:00Z');
        $original = new Organization(42, self::UUID, 'Original', null, null, OrganizationStatus::Active, $time, $time);
        $later = $time->modify('+1 second');
        $changed = $original->updateDetails(' New ', ' Legal ', new OrganizationDocument('529.982.247-25'), $later);
        $inactive = $changed->changeStatus(OrganizationStatus::Inactive, $later);
        $active = $inactive->changeStatus(OrganizationStatus::Active, $later);
        $this->assertSame(42, $active->id);
        $this->assertSame(self::UUID, $active->publicId);
        $this->assertEquals($time, $active->createdAt);
        $this->assertEquals($later, $active->updatedAt);
        $this->assertSame('New', $active->name);
        $this->assertSame('Legal', $active->legalName);
        $this->assertSame('52998224725', $active->document->value);
        $this->assertSame(OrganizationStatus::Inactive, $inactive->status);
        $this->assertSame(OrganizationStatus::Active, $active->status);
        $this->assertSame('Original', $original->name);
        $this->assertNull($active->updateDetails('Clean', null, null, $later)->document);
    }

    public static function invalidNames(): array
    {
        return [[''], ['  '], [str_repeat('á', 256)], ["Invalid\nName"], ["Bad\0Name"]];
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_names_are_rejected(string $name): void
    {
        $this->expectException(InvalidOrganization::class);
        Organization::create(self::UUID, $name, null, null, new DateTimeImmutable);
    }

    public function test_unicode_name_boundary_is_accepted(): void
    {
        $entity = Organization::create(self::UUID, str_repeat('á', 255), null, null, new DateTimeImmutable);
        $this->assertSame(255, mb_strlen($entity->name));
    }

    public function test_invalid_uuid_is_rejected(): void
    {
        $this->expectException(InvalidOrganization::class);
        Organization::create('42', 'Name', null, null, new DateTimeImmutable);
    }

    public function test_invalid_internal_identity_is_rejected(): void
    {
        $time = new DateTimeImmutable;
        $this->expectException(InvalidOrganization::class);
        new Organization(0, self::UUID, 'Name', null, null, OrganizationStatus::Active, $time, $time);
    }

    public function test_updates_cannot_move_time_backwards(): void
    {
        $time = new DateTimeImmutable;
        $entity = Organization::create(self::UUID, 'Name', null, null, $time);
        $this->expectException(InvalidOrganization::class);
        $entity->changeStatus(OrganizationStatus::Inactive, $time->modify('-1 second'));
    }

    public static function validDocuments(): array
    {
        return [['529.982.247-25', '52998224725'], ['11.222.333/0001-81', '11222333000181'], ['12.abc.345/01de-35', '12ABC34501DE35'], ['00.000.000/E08G-12', '00000000E08G12']];
    }

    #[DataProvider('validDocuments')]
    public function test_document_normalization_and_check_digits(string $input, string $expected): void
    {
        $this->assertSame($expected, (new OrganizationDocument($input))->value);
    }

    public static function invalidDocuments(): array
    {
        return [[''], ['00000000000'], ['11111111111'], ['00000000000000'], ['52998224724'], ['11222333000182'], ['12ABC34501DE36'], ['52998224725!'], ['abc']];
    }

    #[DataProvider('invalidDocuments')]
    public function test_invalid_documents_are_rejected(string $value): void
    {
        $this->expectException(InvalidOrganization::class);
        new OrganizationDocument($value);
    }
}
