<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Middleware;

use App\Modules\IdentityAccess\Application\Contracts\SessionAuthentication;
use App\Modules\IdentityAccess\Domain\UserStatus;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureSessionValidity
{
    public function __construct(private SessionAuthentication $authentication) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->authentication->current();
        $authenticatedAt = $request->session()->get('auth.authenticated_at');
        if ($user === null || $user->status !== UserStatus::Active
            || $request->session()->get('auth.identity_version') !== $user->version
            || ! is_int($authenticatedAt) || $authenticatedAt > now()->getTimestamp()
            || now()->getTimestamp() - $authenticatedAt >= config('auth_security.absolute_lifetime_seconds')) {
            $this->authentication->logout();
            throw new AuthenticationException;
        }
        $request->attributes->set('bacurim.authenticated_identity', $user);
        try {
            return $next($request);
        } finally {
            $request->attributes->remove('bacurim.authenticated_identity');
        }
    }
}
