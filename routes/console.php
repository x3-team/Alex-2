<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Доставка принятых заявок в GA4/Метрику (config/analytics.php). Нужен cron с schedule:run.
\Illuminate\Support\Facades\Schedule::command('analytics:deliver')->everyFiveMinutes()->withoutOverlapping();

// Связка заказа сама выходит, пока ORDER_BRIDGE_ENABLED выключен или коды услуг пустые.
\Illuminate\Support\Facades\Schedule::command('order-bridge:push')->everyMinute()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('order-bridge:poll-results')->everyFiveMinutes()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('order-bridge:sync-dictionaries')->hourly()->withoutOverlapping();
