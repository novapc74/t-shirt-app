<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Repositories\CatalogRepository\PgFilterRepository;
use App\Repositories\CatalogRepository\PgCatalogRepository;
use App\Repositories\CatalogRepository\FilterRepositoryInterface;
use App\Repositories\CatalogRepository\CatalogRepositoryInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            FilterRepositoryInterface::class,
            PgFilterRepository::class
        );

        $this->app->bind(
          CatalogRepositoryInterface::class,
            PgCatalogRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
