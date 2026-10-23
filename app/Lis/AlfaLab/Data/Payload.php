<?php

namespace App\Lis\AlfaLab\Data;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Чтение ответов ЛИС без привязки к регистру ключей: в протоколе таблицы пишут поля
 * с заглавной (MessageType, BlankGUID), а JSON-примеры — в camelCase (messageType).
 */
final class Payload
{
    public static function get(?array $data, string $key, mixed $default = null): mixed
    {
        if ($data === null) {
            return $default;
        }
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }
        $needle = strtolower($key);
        foreach ($data as $k => $value) {
            if (is_string($k) && strtolower($k) === $needle) {
                return $value;
            }
        }

        return $default;
    }

    public static function arr(?array $data, string $key): ?array
    {
        $value = self::get($data, $key);

        return is_array($value) ? $value : null;
    }

    /** Множество: [..] либо {"items": [..]} либо {"item": [..]} (как в XML Item). */
    public static function list(?array $data, string $key): array
    {
        $value = self::arr($data, $key);
        if ($value === null) {
            return [];
        }
        if (! array_is_list($value)) {
            $value = self::arr($value, 'items') ?? self::arr($value, 'item') ?? [$value];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    public static function str(?array $data, string $key): ?string
    {
        $value = self::get($data, $key);
        if ($value === null || is_array($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public static function int(?array $data, string $key): ?int
    {
        $value = self::get($data, $key);

        return is_numeric($value) ? (int) $value : null;
    }

    public static function float(?array $data, string $key): ?float
    {
        $value = self::get($data, $key);
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    public static function bool(?array $data, string $key): bool
    {
        $value = self::get($data, $key);
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1'], true);
        }

        return (bool) $value;
    }

    /** JSON: 2021-04-20T17:45:00+03:00 или 2021-04-20; XML: 08.11.2012 14:56:00. */
    public static function date(?array $data, string $key): ?CarbonImmutable
    {
        $value = self::str($data, $key);
        if ($value === null) {
            return null;
        }
        try {
            if (preg_match('/^\d{2}\.\d{2}\.\d{4}( \d{2}:\d{2}:\d{2})?$/', $value)) {
                return CarbonImmutable::createFromFormat(strlen($value) > 10 ? 'd.m.Y H:i:s' : '!d.m.Y', $value, 'Europe/Moscow');
            }

            return CarbonImmutable::parse($value, 'Europe/Moscow');
        } catch (Throwable) {
            return null;
        }
    }
}
