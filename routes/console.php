<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Доставка принятых заявок в GA4/Метрику (config/analytics.php). Нужен cron с schedule:run.
\Illuminate\Support\Facades\Schedule::command('analytics:deliver')->everyFiveMinutes()->withoutOverlapping();

// ЛИС «АльфаЛАБ»: номенклатура и цены раз в сутки. Пока ALFALAB_ENABLED=false, команда ничего не делает.
\Illuminate\Support\Facades\Schedule::command('lis:dictionaries')->dailyAt('04:10')->withoutOverlapping();
