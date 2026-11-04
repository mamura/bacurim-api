<?php

namespace App\Modules\IdentityAccess\Infrastructure\Providers;

use App\Modules\IdentityAccess\Application\Contracts\IdentityClock;
use App\Modules\IdentityAccess\Application\Contracts\IdentityIdGenerator;
use App\Modules\IdentityAccess\Application\Contracts\PasswordChangeAuthorizer;
use App\Modules\IdentityAccess\Application\Contracts\PasswordHasher;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserCredentials;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserRepository;
use App\Modules\IdentityAccess\Infrastructure\Services\DenyPasswordChanges;
use App\Modules\IdentityAccess\Infrastructure\Services\LaravelPasswordHasher;
use App\Modules\IdentityAccess\Infrastructure\Services\SystemIdentityClock;
use App\Modules\IdentityAccess\Infrastructure\Services\UuidIdentityIdGenerator;
use Illuminate\Support\ServiceProvider;

final class IdentityAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->bind(IdentityClock::class, SystemIdentityClock::class);
        $this->app->bind(IdentityIdGenerator::class, UuidIdentityIdGenerator::class);
        $this->app->bind(PasswordChangeAuthorizer::class, DenyPasswordChanges::class);
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(UserCredentials::class, EloquentUserRepository::class);
    }
}
