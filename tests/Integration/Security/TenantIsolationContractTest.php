<?php

namespace Tests\Integration\Security;

use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Security\TenantIsolationContract;
use Tests\Support\Tenancy\ProbeSchema;
use Tests\Support\Tenancy\ProbeUseCases;

#[Group('security')]
class TenantIsolationContractTest extends TenantIsolationContract
{
    private ProbeUseCases $cases;

    protected function prepareStorage(): void
    {
        ProbeSchema::create();
        $this->cases = app(ProbeUseCases::class);
    }

    protected function createResource(TenantContext $context, string $value): string
    {
        return $this->cases->create($context, $value)->public_id;
    }

    protected function readResource(TenantContext $context, string $id): ?string
    {
        try {
            return $this->cases->read($context, $id)->label;
        } catch (ApplicationFailure $failure) {
            if ($failure->kind !== FailureKind::NotFound) {
                throw $failure;
            }

            return null;
        }
    }

    protected function updateResource(TenantContext $context, string $id, string $value): void
    {
        $this->cases->rename($context, $id, $value);
    }

    protected function archiveResource(TenantContext $context, string $id): void
    {
        $this->cases->archive($context, $id);
    }
}
