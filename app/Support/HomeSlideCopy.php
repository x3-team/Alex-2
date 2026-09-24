<?php

namespace App\Support;

/**
 * Wording of the home deck slides that used to live only in Welcome.vue.
 *
 * These are the exact texts production shows today. They seed the CMS so the
 * page looks the same after the release, and they stay as the front-end
 * fallback for a field the editor leaves empty.
 */
class HomeSlideCopy
{
    /** Fields this class owns, in the order they appear on the page. */
    public const FIELDS = ['hero_title', 'why_title', 'results_intro_title', 'cta_text'];

    /** @return array<string, string> */
    public static function defaults(string $audience): array
    {
        $copy = [
            'patient' => [
                // slide-1
                'hero_title' => 'Тест на аллергию ALEX² — один анализ, который даёт ответы',
                // slide-2
                'why_title' => 'Почему ALEX2?',
                // slide-8
                'results_intro_title' => 'Что вы получите по итогам теста на аллергию',
                'cta_text' => 'Записаться на тест на аллергию',
            ],
            'doctor' => [
                'hero_title' => 'Аллергочип ALEX² — расширенный анализ на аллергию. 300+ аллергенов',
                'why_title' => 'ALEX² — лучший тест на аллергию, что есть на рынке.',
                'results_intro_title' => 'Как назначать тест пациентам',
                'cta_text' => 'Записаться на тест на аллергию',
            ],
        ];

        return $copy[$audience] ?? $copy['patient'];
    }

    /**
     * Fields that still need seeding, so a text the editor already changed is
     * never overwritten.
     *
     * @param  array<string, mixed>  $current  values stored for this audience
     * @return array<string, string> only the fields to write
     */
    public static function fillMissing(array $current, string $audience): array
    {
        $missing = [];

        foreach (self::defaults($audience) as $field => $text) {
            $stored = $current[$field] ?? null;
            if (! is_string($stored) || trim($stored) === '') {
                $missing[$field] = $text;
            }
        }

        return $missing;
    }
}
