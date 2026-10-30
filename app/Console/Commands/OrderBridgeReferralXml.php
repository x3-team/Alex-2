<?php

namespace App\Console\Commands;

use App\Models\LabOrder;
use App\Support\OrderBridge\AccessLogger;
use App\Support\OrderBridge\BridgeConfig;
use App\Support\OrderBridge\DnkomXml;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

class OrderBridgeReferralXml extends Command
{
    protected $signature = 'order-bridge:referral-xml {lab_order_id}';

    protected $description = 'Печатает XML заявки ДНКОМ (windows-1251) без HTTP-отправки';

    public function handle(): int
    {
        $order = LabOrder::query()->with(['patient', 'lines'])->find($this->argument('lab_order_id'));
        if (! $order || ! $order->patient) {
            $this->error('Заказ не найден');

            return self::FAILURE;
        }

        AccessLogger::write($order->lab_patient_id, $order->id, 'read', 'order-bridge:referral-xml');

        if (! BridgeConfig::outboundReady()) {
            $this->error('Коды услуг или реквизиты пустые: такой XML не будет отправлен.');
        }
        if ($order->payment_mode === 'online' && $order->payment_status !== 'paid') {
            $this->error('Онлайн-оплата ещё не прошла: XML ниже уйдёт только после оплаты.');
        }

        $this->getOutput()->write(DnkomXml::referral($order), false, OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }
}
