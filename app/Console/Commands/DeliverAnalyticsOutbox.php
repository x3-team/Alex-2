<?php

namespace App\Console\Commands;

use App\Models\AnalyticsOutbox;
use App\Support\Analytics\OutboxSender;
use Illuminate\Console\Command;

// Повторная доставка принятых заявок в GA4/Метрику: то, что не ушло сразу после ответа
// (нет ключа, сбой сети, ошибка платформы). Запускается планировщиком раз в 5 минут.
class DeliverAnalyticsOutbox extends Command
{
    protected $signature = 'analytics:deliver {--limit=100}';

    protected $description = 'Отправить в GA4 и Метрику накопившиеся события из analytics_outbox';

    public function handle(OutboxSender $sender): int
    {
        $rows = AnalyticsOutbox::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', (int) config('analytics.max_attempts', 5))
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($rows as $row) {
            $sender->deliver($row);
        }

        $this->info('Обработано: '.$rows->count());

        return self::SUCCESS;
    }
}
