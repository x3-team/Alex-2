<?php

namespace App\Lis\AlfaLab;

use App\Models\Lis\LisExchangeLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Журнал обмена с ЛИС. Пишем тип сообщения, исход, код, время и ссылки на заказ.
 * Тела запросов и ответов не пишем: там ФИО, дата рождения и результаты.
 * Текст ошибки от ЛИС может содержать данные пациента, поэтому чистим e-mail и цифры.
 */
class ExchangeLogger
{
    public function record(
        string $messageType,
        string $outcome,
        ?int $httpStatus,
        int $durationMs,
        ?string $misId = null,
        ?int $lisId = null,
        ?string $error = null,
        ?string $requestBody = null,
    ): void {
        try {
            LisExchangeLog::create([
                'message_type' => $messageType,
                'outcome' => $outcome,
                'http_status' => $httpStatus,
                'duration_ms' => $durationMs,
                'mis_id' => $misId,
                'lis_id' => $lisId,
                'error' => $error === null ? null : mb_substr(self::scrub($error), 0, 500),
                'request_hash' => $requestBody === null ? null : hash('sha256', $requestBody),
            ]);
        } catch (Throwable $e) {
            // Журнал не должен ронять обмен.
            Log::warning('lis_exchange_log write failed: '.$e->getMessage());
        }
    }

    public static function scrub(string $text): string
    {
        $text = preg_replace('/[^\s@]+@[^\s@]+/u', '[email]', $text) ?? $text;
        // Даты рождения и телефоны. Номера направлений (8, 10, 12 цифр подряд) оставляем — по ним ищем.
        $text = preg_replace('/\b\d{2}[.\-\/]\d{2}[.\-\/]\d{4}\b/u', '[date]', $text) ?? $text;

        return preg_replace_callback(
            '/\+?\d[\d\s()\-]{8,}\d/u',
            fn ($m) => preg_match('/^\d{8}$|^\d{10}$|^\d{12}$/', $m[0]) ? $m[0] : '[phone]',
            $text,
        ) ?? $text;
    }

    public function prune(int $days): int
    {
        return LisExchangeLog::where('created_at', '<', now()->subDays($days))->delete();
    }
}
