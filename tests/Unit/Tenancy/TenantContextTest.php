<?php

namespace Tests\Unit\Tenancy;

use App\SharedKernel\Application\Tenancy\TenantContext;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class TenantContextTest extends TestCase
{
    private const UUID = 'c4ca63e2-92a4-4c17-bf34-c08753927800';

    public function test_context_is_immutable_explicit_and_canonical(): void
    {
        $context = new TenantContext(3, strtoupper(self::UUID), self::UUID, 2, self::UUID, 4);
        $this->assertSame(self::UUID, $context->organizationPublicId);
        $this->assertSame(3, $context->organizationId);
        $this->assertSame(2, $context->actorVersion);
        $this->assertSame(4, $context->membershipVersion);
        $this->assertTrue((new ReflectionClass($context))->isReadOnly());
        $this->assertFalse(property_exists($context, 'role'));
    }

    public static function invalidContexts(): array
    {
        $id = self::UUID;

        return [[0, $id, $id, 1, $id, 1], [1, $id, $id, 0, $id, 1], [1, $id, $id, 1, $id, 0], [1, 'bad', $id, 1, $id, 1], [1, $id, '', 1, $id, 1], [1, $id, $id, 1, '1', 1]];
    }

    #[DataProvider('invalidContexts')]
    public function test_invalid_references_are_rejected(int $org, string $orgUuid, string $actor, int $actorVersion, string $membership, int $membershipVersion): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TenantContext($org, $orgUuid, $actor, $actorVersion, $membership, $membershipVersion);
    }
}
