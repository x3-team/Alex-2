<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Parses admin "published at" input: datetime-local is interpreted in admin TZ (MSK),
 * ISO strings with Z/offset are stored as absolute UTC.
 */
final class AdminPublishedAt
{
    public static function parse(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/[Zz]$|[+\-]\d{2}:\d{2}$/', $value)) {
            return Carbon::parse($value)->utc();
        }

        $tz = config('app.admin_timezone', 'Europe/Moscow');

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
            try {
                return Carbon::createFromFormat('Y-m-d\TH:i', $value, $tz)->utc();
            } catch (InvalidFormatException) {
                return Carbon::parse($value, $tz)->utc();
            }
        }

        return Carbon::parse($value, $tz)->utc();
    }
}
