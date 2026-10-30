<?php

namespace App\Support\OrderBridge;

use App\Models\LabOrder;

class VisitNote
{
    public static function text(LabOrder $order, ?string $dnkomNr): string
    {
        $day = $order->visit_date?->format('d.m.Y') ?? '';
        $lines = ['Желаемое время визита: '.$day.' '.$order->visit_time];
        if (is_string($dnkomNr) && $dnkomNr !== '') {
            $lines[] = 'Номер заявки ДНКОМ: '.$dnkomNr;
        }

        return implode('. ', $lines);
    }
}
