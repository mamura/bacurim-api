<?php

namespace App\Modules\Financial\Infrastructure\Providers;

use App\Modules\Financial\Application\Contracts\FinancialClock;
use App\Modules\Financial\Application\Contracts\FinancialIds;
use App\Modules\Financial\Application\Contracts\Persistence\FinancialAccountRepository;
use App\Modules\Financial\Application\Contracts\Persistence\FinancialCategoryRepository;
use App\Modules\Financial\Infrastructure\Persistence\Eloquent\Repositories\EloquentFinancialAccountRepository;
use App\Modules\Financial\Infrastructure\Persistence\Eloquent\Repositories\EloquentFinancialCategoryRepository;
use App\Modules\Financial\Infrastructure\Services\SystemFinancialClock;
use App\Modules\Financial\Infrastructure\Services\UuidFinancialIds;
use Illuminate\Support\ServiceProvider;

final class FinancialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FinancialClock::class, SystemFinancialClock::class);
        $this->app->bind(FinancialIds::class, UuidFinancialIds::class);
        $this->app->bind(FinancialCategoryRepository::class, EloquentFinancialCategoryRepository::class);
        $this->app->bind(FinancialAccountRepository::class, EloquentFinancialAccountRepository::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../Interfaces/Http/routes.php');
    }
}
