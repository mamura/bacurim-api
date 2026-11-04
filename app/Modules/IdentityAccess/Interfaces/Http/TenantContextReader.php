<?php

namespace App\Modules\IdentityAccess\Interfaces\Http;

use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Http\Request;

final readonly class TenantContextReader
{
    public function __construct(private Request $request) {}

    public function get(): TenantContext
    {
        $context = $this->request->attributes->get(TenantContext::class);

        return $context instanceof TenantContext ? $context : throw new ApplicationFailure(FailureKind::Forbidden, 'tenant.context_required', 'A validated organization context is required.');
    }
}
