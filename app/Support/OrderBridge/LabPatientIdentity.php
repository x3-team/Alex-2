<?php

namespace App\Support\OrderBridge;

use DateTime;

class LabPatientIdentity
{
    public static function split(string $full): ?array
    {
        $parts = preg_split('/\s+/u', trim($full), -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts) || count($parts) < 2) {
            return null;
        }

        return [
            'last_name' => $parts[0],
            'first_name' => $parts[1],
            'middle_name' => count($parts) > 2 ? implode(' ', array_slice($parts, 2)) : null,
        ];
    }

    public static function tenDigits(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10 ? $digits : null;
    }

    public static function date(string $value): ?string
    {
        $value = trim($value);
        foreach (['d.m.Y', 'Y-m-d'] as $format) {
            $dt = DateTime::createFromFormat('!'.$format, $value);
            $errors = DateTime::getLastErrors();
            $clean = $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
            if ($dt && $clean && $dt->format($format) === $value) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    public static function time(string $value): ?string
    {
        $value = trim($value);

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? $value : null;
    }

    public static function timeTo(string $from, int $minutes): string
    {
        [$hour, $minute] = array_map('intval', explode(':', $from));
        $total = min($hour * 60 + $minute + max($minutes, 1), 23 * 60 + 59);

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    public static function phoneHash(string $phone): string
    {
        return self::hmac($phone);
    }

    public static function identityHash(string $lastName, string $birthDate): string
    {
        return self::hmac(mb_strtolower(trim($lastName)).'|'.$birthDate);
    }

    private static function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
