<?php

namespace Tests\Unit\Tenancy;

use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantCapability;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantJobIdentity;
use App\Modules\IdentityAccess\Domain\MembershipPermission;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('security')]
class TenantPublicContractTest extends TestCase
{
    public function test_public_capabilities_refer_to_the_existing_policy_vocabulary(): void
    {
        $this->assertSame(array_column(MembershipPermission::cases(), 'value'), array_column(TenantCapability::cases(), 'value'));
    }

    public static function invalidJobs(): array
    {
        return [['', 'c4ca63e2-92a4-4c17-bf34-c08753927800'], ['c4ca63e2-92a4-4c17-bf34-c08753927800', 'bad']];
    }

    #[DataProvider('invalidJobs')]
    public function test_job_identifiers_are_structurally_validated(string $organization, string $actor): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TenantJobIdentity($organization, $actor);
    }
}
