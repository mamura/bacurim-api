<?php

namespace App\Modules\IdentityAccess\Application\Contracts\PublicApi;

use App\SharedKernel\Application\Tenancy\TenantContext;
use InvalidArgumentException;
use JsonSerializable;

/** Queue routing metadata, never a persisted authorization decision. */
final readonly class TenantJobIdentity implements JsonSerializable
{
    public string $organizationPublicId;

    public string $actorPublicId;

    public function __construct(string $organizationPublicId, string $actorPublicId)
    {
        foreach ([$organizationPublicId, $actorPublicId] as $id) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $id) !== 1) {
                throw new InvalidArgumentException('Job organization and actor must be UUIDs.');
            }
        }
        $this->organizationPublicId = strtolower($organizationPublicId);
        $this->actorPublicId = strtolower($actorPublicId);
    }

    public static function fromContext(TenantContext $context): self
    {
        return new self($context->organizationPublicId, $context->actorPublicId);
    }

    /** @return array{organization_public_id:string,actor_public_id:string} */
    public function jsonSerialize(): array
    {
        return ['organization_public_id' => $this->organizationPublicId, 'actor_public_id' => $this->actorPublicId];
    }
}
