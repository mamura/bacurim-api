<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Middleware;

use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Application\UseCases\ResolveTenantContext;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveTenant
{
    public function __construct(private ResolveTenantContext $resolve) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->remove(TenantContext::class);
        try {
            $identity = $request->attributes->get('bacurim.authenticated_identity');
            if (! $identity instanceof UserData) {
                throw new AuthenticationException;
            }
            $organization = $request->route('organization');
            if (! is_string($organization) || $organization === '') {
                throw new ApplicationFailure(FailureKind::Validation, 'tenant.organization_required', 'An organization route identifier is required.');
            }
            $context = $this->resolve->execute($organization, $identity);
            $request->attributes->set(TenantContext::class, $context);

            return $next($request);
        } finally {
            $request->attributes->remove(TenantContext::class);
        }
    }
}
