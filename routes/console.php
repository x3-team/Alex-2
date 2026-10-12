<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Доставка принятых заявок в GA4/Метрику (config/analytics.php). Нужен cron с schedule:run.
\Illuminate\Support\Facades\Schedule::command('analytics:deliver')->everyFiveMinutes()->withoutOverlapping();
