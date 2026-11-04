<?php

namespace Tests\Support\Tenancy;

use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantCapability;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantJobIdentity;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantJobRunner;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Test fixture only; no financial feature or production queue is registered. */
final readonly class TenantProbeJob implements ShouldQueue
{
    public function __construct(public TenantJobIdentity $identity, public string $label) {}

    public function handle(TenantJobRunner $runner): void
    {
        $runner->run($this->identity, TenantCapability::WriteFinancial, fn (TenantContext $context) => (new ScopedProbeRepository)->create($context, $this->label));
    }
}
