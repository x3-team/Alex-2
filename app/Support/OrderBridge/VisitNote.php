<?php

namespace App\Support\OrderBridge;

use App\Models\LabOrder;

class VisitNote
{
    public static function text(LabOrder $order, ?string $dnkomNr): string
    {
        $day = $order->visit_date?->format('d.m.Y') ?? '';
        $lines = ['Желаемое время визита: '.$day.' '.$order->visit_time];
        if ($order->is_test) {
            array_unshift($lines, 'TEST: тестовый заказ с сайта, не выполнять');
        }
        if (is_string($dnkomNr) && $dnkomNr !== '') {
            $lines[] = 'Номер заявки ДНКОМ: '.$dnkomNr;
        }

        return implode('. ', $lines);
    }
}
