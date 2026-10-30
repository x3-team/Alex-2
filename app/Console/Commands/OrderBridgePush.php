<?php

namespace App\Console\Commands;

use App\Models\LabOutboundJob;
use App\Support\OrderBridge\BridgeConfig;
use App\Support\OrderBridge\LabOrderPush;
use Illuminate\Console\Command;

class OrderBridgePush extends Command
{
    protected $signature = 'order-bridge:push';

    protected $description = 'Отправить в Medmis и ДНКОМ заказы, которые ещё не ушли';

    public function handle(LabOrderPush $push): int
    {
        if (! BridgeConfig::backgroundEnabled()) {
            return self::SUCCESS;
        }

        $push->reclaim();
        $push->resurrect();

        $ids = LabOutboundJob::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', (int) config('order_bridge.max_attempts'))
            ->orderBy('id')
            ->limit(20)
            ->pluck('id');

        foreach ($ids as $id) {
            $push->run((int) $id);
        }

        return self::SUCCESS;
    }
}
