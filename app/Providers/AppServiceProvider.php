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
        // Свой SSR-шлюз: короткий таймаут, лог отказов, статус для X-SSR и запасного текста.
        $this->app->scoped(\App\Support\Ssr\SsrStatus::class);
        $this->app->singleton(\Inertia\Ssr\Gateway::class, \App\Support\Ssr\ResilientSsrGateway::class);

        // ЛИС «АльфаЛАБ»: транспорт по ALFALAB_DRIVER (fake — эмулятор без стенда, http — стенд/бой).
        $this->app->singleton(\App\Lis\AlfaLab\Transport\LisTransport::class, fn () => config('alfalab.driver') === 'http'
            ? new \App\Lis\AlfaLab\Transport\HttpTransport(config('alfalab.http', []))
            : new \App\Lis\AlfaLab\Transport\FakeTransport(config('alfalab', [])));
        $this->app->singleton(\App\Lis\AlfaLab\AlfaLabClient::class, fn ($app) => new \App\Lis\AlfaLab\AlfaLabClient(
            $app->make(\App\Lis\AlfaLab\Transport\LisTransport::class),
            $app->make(\App\Lis\AlfaLab\ExchangeLogger::class),
            config('alfalab.hospital_code') ?: null,
        ));
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
