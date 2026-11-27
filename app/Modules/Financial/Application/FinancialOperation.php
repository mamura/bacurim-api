<?php

namespace App\Modules\Financial\Application;

use App\Modules\Financial\Domain\Exceptions\InvalidFinancialData;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantCapability;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantOperation;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;

final readonly class FinancialOperation
{
    public function __construct(private TenantOperation $tenants) {}

    /**
     * @template T
     *
     * @param  callable():T  $operation
     * @return T
     */
    public function run(TenantContext $context, bool $write, callable $operation): mixed
    {
        try {
            return $this->tenants->run($context, $write ? TenantCapability::WriteFinancial : TenantCapability::ReadFinancial, $operation);
        } catch (InvalidFinancialData $failure) {
            throw new ApplicationFailure(FailureKind::Validation, 'financial.invalid', $failure->getMessage());
        }
    }

    public function missing(): never
    {
        throw new ApplicationFailure(FailureKind::NotFound, 'financial.not_found', 'Financial resource not found.');
    }

    public function conflict(string $code, string $message): never
    {
        throw new ApplicationFailure(FailureKind::Conflict, $code, $message);
    }
}
