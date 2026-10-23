<?php

namespace App\Console\Commands;

use App\Lis\AlfaLab\DictionarySync;
use App\Lis\AlfaLab\ExchangeLogger;
use App\Lis\AlfaLab\Exceptions\LisException;
use Illuminate\Console\Command;

class LisDictionaries extends Command
{
    protected $signature = 'lis:dictionaries
                            {--force : Скачать номенклатуру, даже если версия не изменилась}';

    protected $description = 'Синхронизирует номенклатуру и цены ЛИС «АльфаЛАБ» и проверяет, можно ли заказывать ALEX2';

    public function handle(DictionarySync $sync, ExchangeLogger $logger): int
    {
        if (! config('alfalab.enabled')) {
            $this->line('ЛИС выключена (ALFALAB_ENABLED=false), пропускаю.');

            return self::SUCCESS;
        }

        try {
            $result = $sync->sync((bool) $this->option('force'));
        } catch (LisException $e) {
            $this->error("ЛИС: {$e->kind}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Номенклатура v%d: %s, услуг в кэше: %d',
            $result['version'],
            $result['changed'] ? 'обновлена' : 'без изменений',
            $result['services'],
        ));

        $logger->prune((int) config('alfalab.log_retention_days', 90));

        $problems = DictionarySync::problems();
        foreach ($problems as $problem) {
            $this->warn($problem);
        }

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }
}
