<?php

namespace Tests\Support\Tenancy;

use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class DowngradeTenantActor
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = $request->attributes->get(TenantContext::class);
        if (! $context instanceof TenantContext) {
            throw new LogicException('Test middleware requires tenant context.');
        }
        MembershipRecord::query()->where('organization_id', $context->organizationId)->where('public_id', $context->membershipPublicId)->update(['role' => 'viewer', 'version' => $context->membershipVersion + 1]);

        return $next($request);
    }
}
