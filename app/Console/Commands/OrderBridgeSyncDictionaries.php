<?php

namespace App\Console\Commands;

use App\Support\OrderBridge\DictionarySync;
use App\Support\OrderBridge\LabBridgeException;
use Illuminate\Console\Command;

class OrderBridgeSyncDictionaries extends Command
{
    protected $signature = 'order-bridge:sync-dictionaries';

    protected $description = 'Сверить версию справочников ДНКОМ и скачать их, если она изменилась';

    public function handle(DictionarySync $sync): int
    {
        try {
            $sync->run();
        } catch (LabBridgeException) {
            return self::SUCCESS;
        }

        return self::SUCCESS;
    }
}
