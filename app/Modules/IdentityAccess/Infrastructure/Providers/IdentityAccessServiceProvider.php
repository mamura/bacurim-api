<?php

namespace App\Modules\IdentityAccess\Infrastructure\Providers;

use App\Modules\IdentityAccess\Application\Contracts\PasswordHasher;
use App\Modules\IdentityAccess\Infrastructure\Services\LaravelPasswordHasher;
use Illuminate\Support\ServiceProvider;

final class IdentityAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
    }
}
