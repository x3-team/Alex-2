<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prefetch admin editor chunks only inside /admin.
        // Public pages and /login must not modulepreload TiptapEditor.
        if (! $this->app->runningInConsole() && request()->is('admin', 'admin/*')) {
            Vite::prefetch(concurrency: 3);
        }
    }
}
