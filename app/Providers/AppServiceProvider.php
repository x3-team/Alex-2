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

        // Оплата: ЮKassa или эмулятор без ключей (PAYMENTS_DRIVER=fake).
        $this->app->singleton(\App\Payments\Gateway\PaymentGateway::class, fn () => config('payments.driver') === 'yookassa'
            ? new \App\Payments\Gateway\YooKassaGateway(config('payments.yookassa', []), config('payments.receipt', []))
            : new \App\Payments\Gateway\FakeGateway);
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
