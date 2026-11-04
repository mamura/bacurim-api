<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class GuardSpaOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/v1/auth/*', 'sanctum/csrf-cookie')) {
            return $next($request);
        }
        $origin = $request->header('Origin');
        if ($origin === null && $request->header('Referer') !== null) {
            $parts = parse_url($request->header('Referer'));
            if (is_array($parts) && isset($parts['scheme'], $parts['host']) && ! isset($parts['user']) && ! isset($parts['pass'])) {
                $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            }
        }
        if ($origin === null && $request->isMethod('GET') && $request->is('api/v1/auth/me')) {
            return $next($request);
        }
        if ($origin === null || ! in_array($origin, config('cors.allowed_origins', []), true) || ! EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            throw new HttpException(403);
        }

        return $next($request);
    }
}
