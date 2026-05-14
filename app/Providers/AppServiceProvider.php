<?php

namespace App\Providers;

use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\EloquentAccountRepository;
use App\Services\Contracts\TransactionDemoServiceInterface;
use App\Services\TransactionDemoService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AccountRepositoryInterface::class, EloquentAccountRepository::class);
        $this->app->bind(TransactionDemoServiceInterface::class, TransactionDemoService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
