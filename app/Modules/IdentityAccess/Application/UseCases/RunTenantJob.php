<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantAuthorization;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantCapability;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantJobIdentity;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantJobRunner;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Application\TenantExecution;
use App\SharedKernel\Application\Tenancy\TenantContext;

final readonly class RunTenantJob implements TenantJobRunner
{
    public function __construct(private TenantExecution $execution, private ResolveTenantContext $resolve, private TenantAuthorization $authorization) {}

    /**
     * @template T
     *
     * @param  callable(TenantContext):T  $operation
     * @return T
     */
    public function run(TenantJobIdentity $identity, TenantCapability $required, callable $operation): mixed
    {
        return $this->execution->transaction->run(function () use ($identity, $required, $operation) {
            $org = $this->execution->organization($identity->organizationPublicId);
            $this->execution->lockOrganizationUsers($org, [$identity->actorPublicId]);
            $user = $this->execution->users->get($identity->actorPublicId);
            $context = $this->resolve->execute($org->publicId, UserData::fromEntity($user));
            $this->authorization->authorize($context, $required);

            return $operation($context);
        });
    }
}
