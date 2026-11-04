<?php

namespace App\SharedKernel\Application\Tenancy;

use InvalidArgumentException;

final readonly class TenantContext
{
    public string $organizationPublicId;

    public string $actorPublicId;

    public string $membershipPublicId;

    public function __construct(
        public int $organizationId, string $organizationPublicId, string $actorPublicId,
        public int $actorVersion, string $membershipPublicId, public int $membershipVersion,
    ) {
        if ($organizationId < 1 || $actorVersion < 1 || $membershipVersion < 1) {
            throw new InvalidArgumentException('Tenant references and versions must be positive.');
        }
        foreach ([$organizationPublicId, $actorPublicId, $membershipPublicId] as $id) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $id) !== 1) {
                throw new InvalidArgumentException('Tenant public references must be UUIDs.');
            }
        }
        $this->organizationPublicId = strtolower($organizationPublicId);
        $this->actorPublicId = strtolower($actorPublicId);
        $this->membershipPublicId = strtolower($membershipPublicId);
    }
}
