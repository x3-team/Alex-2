<?php

namespace App\Payments\Data;

/** Деньги в копейках: без ошибок округления float. ЮKassa принимает строку «24900.00». */
final class Money
{
    public static function fromRubles(string|int|float $rubles): int
    {
        return (int) round(((float) str_replace(',', '.', (string) $rubles)) * 100);
    }

    public static function format(int $kopecks): string
    {
        return sprintf('%d.%02d', intdiv($kopecks, 100), $kopecks % 100);
    }

    public static function toRubles(int $kopecks): string
    {
        return self::format($kopecks);
    }
}
