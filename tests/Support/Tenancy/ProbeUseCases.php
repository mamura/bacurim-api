<?php

namespace Tests\Support\Tenancy;

use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantCapability;
use App\Modules\IdentityAccess\Application\TenantExecution;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;

/** Test-only consumer of the context contract; not a financial use case. */
final readonly class ProbeUseCases
{
    public function __construct(private TenantExecution $execution, private ScopedProbeRepository $repository) {}

    public function read(TenantContext $context, string $publicId): object
    {
        return $this->run($context, TenantCapability::ReadFinancial, fn () => $this->repository->find($context, $publicId) ?? throw new ApplicationFailure(FailureKind::NotFound, 'probe.not_found', 'Resource not found.'));
    }

    public function create(TenantContext $context, string $label): object
    {
        return $this->run($context, TenantCapability::WriteFinancial, function () use ($context, $label): object {
            $id = $this->repository->create($context, $label);

            return $this->repository->find($context, $id);
        });
    }

    public function rename(TenantContext $context, string $publicId, string $label): object
    {
        return $this->run($context, TenantCapability::WriteFinancial, function () use ($context, $publicId, $label): object {
            $this->repository->rename($context, $publicId, $label);

            return $this->repository->find($context, $publicId);
        });
    }

    public function archive(TenantContext $context, string $publicId): void
    {
        $this->run($context, TenantCapability::WriteFinancial, fn () => $this->repository->archive($context, $publicId));
    }

    private function run(TenantContext $context, TenantCapability $required, callable $operation): mixed
    {
        return $this->execution->transaction->run(function () use ($context, $required, $operation) {
            $this->execution->forContext($context, $required);

            return $operation();
        });
    }
}
