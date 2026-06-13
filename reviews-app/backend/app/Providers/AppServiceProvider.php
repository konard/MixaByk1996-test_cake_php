<?php

namespace App\Providers;

use App\Services\YandexMapsParser;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(YandexMapsParser::class);
    }

    public function boot(): void
    {
        //
    }
}
