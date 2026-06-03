<?php

namespace App\Providers;

use App\Models\CatalogItem;
use App\Observers\CatalogItemObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        CatalogItem::observe(CatalogItemObserver::class);
    }
}
