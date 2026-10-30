<?php

namespace App\Console\Commands;

use App\Support\OrderBridge\LabBridgeException;
use App\Support\OrderBridge\LabResultPoll;
use Illuminate\Console\Command;

class OrderBridgePollResults extends Command
{
    protected $signature = 'order-bridge:poll-results';

    protected $description = 'Забрать следующие результаты ДНКОМ и PDF бланка';

    public function handle(LabResultPoll $poll): int
    {
        try {
            $poll->run();
        } catch (LabBridgeException) {
            return self::SUCCESS;
        }

        return self::SUCCESS;
    }
}
