<?php

namespace App\Support;

use App\Models\Setting;

class LeadRecipients
{
    /**
     * Куда слать письма о заявках: настройка админки, затем LEADS_MAIL_TO, затем info@.
     *
     * @return list<string>
     */
    public static function addresses(): array
    {
        $fromSetting = self::parse(Setting::get('leads_mail_to'));
        if ($fromSetting !== []) {
            return $fromSetting;
        }

        $fromEnv = self::parse(config('leads.mail_to'));
        if ($fromEnv !== []) {
            return $fromEnv;
        }

        return [self::fallback()];
    }

    public static function fallback(): string
    {
        $fallback = config('leads.fallback', 'info@alexallergotest.ru');

        return is_string($fallback) && filter_var($fallback, FILTER_VALIDATE_EMAIL)
            ? $fallback
            : 'info@alexallergotest.ru';
    }

    /**
     * @return list<string>
     */
    public static function parse(mixed $raw): array
    {
        if (! is_string($raw)) {
            return [];
        }

        $emails = [];
        foreach (preg_split('/[,;]+/', $raw) ?: [] as $part) {
            $email = trim($part);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($email);
            }
        }

        return array_values(array_unique($emails));
    }
}
