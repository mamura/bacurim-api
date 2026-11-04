<?php

namespace App\Modules\IdentityAccess\Infrastructure\Providers;

use App\Modules\IdentityAccess\Application\Contracts\IdentityClock;
use App\Modules\IdentityAccess\Application\Contracts\IdentityIdGenerator;
use App\Modules\IdentityAccess\Application\Contracts\PasswordChangeAuthorizer;
use App\Modules\IdentityAccess\Application\Contracts\PasswordHasher;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserCredentials;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Application\Contracts\SessionAuthentication;
use App\Modules\IdentityAccess\Application\UseCases\GetUser;
use App\Modules\IdentityAccess\Infrastructure\Auth\IdentityUserProvider;
use App\Modules\IdentityAccess\Infrastructure\Auth\LaravelSessionAuthentication;
use App\Modules\IdentityAccess\Infrastructure\Auth\RequestPasswordChangeAuthorizer;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserRepository;
use App\Modules\IdentityAccess\Infrastructure\Services\LaravelPasswordHasher;
use App\Modules\IdentityAccess\Infrastructure\Services\SystemIdentityClock;
use App\Modules\IdentityAccess\Infrastructure\Services\UuidIdentityIdGenerator;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

final class IdentityAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SessionAuthentication::class, function ($app) {
            $guard = $app['auth']->guard('web');
            if (! $guard instanceof SessionGuard) {
                throw new \LogicException('Session guard required.');
            }

            return new LaravelSessionAuthentication($guard, $app->make(GetUser::class));
        });
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->bind(IdentityClock::class, SystemIdentityClock::class);
        $this->app->bind(IdentityIdGenerator::class, UuidIdentityIdGenerator::class);
        $this->app->bind(PasswordChangeAuthorizer::class, RequestPasswordChangeAuthorizer::class);
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(UserCredentials::class, EloquentUserRepository::class);
    }

    public function boot(): void
    {
        TrustProxies::at(config('auth_security.trusted_proxies'));
        RateLimiter::for('identity-login', static function (Request $request) {
            $email = $request->input('email');
            $normalized = is_string($email) ? strtolower(trim($email)) : 'invalid';
            $secret = (string) config('app.key');
            $pair = hash_hmac('sha256', $normalized.'|'.$request->ip(), $secret);
            $ip = hash_hmac('sha256', (string) $request->ip(), $secret);

            return [
                Limit::perMinute(5)->by('login-pair:'.$pair),
                Limit::perMinute(30)->by('login-ip:'.$ip),
            ];
        });
        $this->loadRoutesFrom(__DIR__.'/../../Interfaces/Http/routes.php');
        Auth::provider('bacurim-identity', function ($app, array $config) {
            return new IdentityUserProvider(
                $app['hash'], $config['model'], $app->make(PasswordHasher::class),
                $app->make(UserCredentials::class), $app->make(IdentityClock::class),
            );
        });
        Sanctum::getAccessTokenFromRequestUsing(static fn (Request $request): ?string => null);
    }
}
