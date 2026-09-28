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

    /**
     * Instant stored when an admin saves a post.
     *
     * Turning a draft on publishes at the moment of that save. A clock carried
     * over from the draft is ignored unless the editor changed the date field.
     * An article that is already on the site keeps its date until that field changes.
     *
     * @param  Carbon|string|null  $existingPublishedAt
     */
    public static function forSave(
        bool $willBeActive,
        bool $wasActive,
        mixed $existingPublishedAt,
        ?string $postedValue,
        bool $dateEdited,
    ): ?Carbon {
        if (! $willBeActive) {
            return self::existingInstant($existingPublishedAt);
        }

        if ($dateEdited) {
            return self::parse($postedValue) ?? now()->utc();
        }

        $existing = self::existingInstant($existingPublishedAt);
        if (! $wasActive || $existing === null) {
            return now()->utc();
        }

        return $existing;
    }

    private static function existingInstant(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        return Carbon::parse((string) $value)->utc();
    }
}
