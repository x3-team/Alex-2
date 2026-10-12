<?php

namespace App\Support\Analytics;

use App\Models\AnalyticsOutbox;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Доставка строк analytics_outbox: GA4 — Measurement Protocol, Метрика — офлайн-конверсии
 * по ClientID. В журнал пишется только итог и код причины, без тела ответа платформы.
 */
class OutboxSender
{
    // Имя события в каждой платформе (ТЗ §4.1: lead_accepted → generate_lead в GA4).
    private const GA4_EVENTS = ['lead_accepted' => 'generate_lead'];

    public function deliver(AnalyticsOutbox $row): void
    {
        if (! in_array($row->status, ['pending', 'failed'], true)) {
            return;
        }

        // Без согласия на аналитику в момент заявки ничего не отправляем.
        if ($row->consent !== 'granted') {
            $this->finish($row, 'suppressed', 'no_consent');

            return;
        }

        try {
            [$status, $reason] = match ($row->platform) {
                'ga4' => $this->sendGa4($row),
                'metrika' => $this->sendMetrika($row),
                default => ['suppressed', 'unknown_platform'],
            };
        } catch (Throwable) {
            [$status, $reason] = ['failed', 'network'];
        }

        if ($status === 'failed' && $row->attempts + 1 >= (int) config('analytics.max_attempts', 5)) {
            $reason = 'gave_up_'.$reason;
        }

        $this->finish($row, $status, $reason, $status === 'failed');
    }

    private function sendGa4(AnalyticsOutbox $row): array
    {
        $secret = config('analytics.ga4.api_secret');
        if (! $secret) {
            return ['pending', 'not_configured'];
        }

        $response = Http::timeout(5)->asJson()->post(
            'https://www.google-analytics.com/mp/collect?'.http_build_query([
                'measurement_id' => config('analytics.ga4.measurement_id'),
                'api_secret' => $secret,
            ]),
            [
                'client_id' => $row->client_id,
                'timestamp_micros' => $row->occurred_at->getTimestamp() * 1000000,
                'events' => [[
                    'name' => self::GA4_EVENTS[$row->event_name] ?? $row->event_name,
                    'params' => $row->params + ['engagement_time_msec' => 1],
                ]],
            ]
        );

        return $response->successful() ? ['sent', null] : ['failed', 'http_'.$response->status()];
    }

    private function sendMetrika(AnalyticsOutbox $row): array
    {
        $token = config('analytics.metrika.oauth_token');
        if (! $token) {
            return ['pending', 'not_configured'];
        }

        $csv = "ClientId,Target,DateTime\n".$row->client_id.','.$row->event_name.','.$row->occurred_at->getTimestamp()."\n";

        $response = Http::timeout(10)
            ->withHeaders(['Authorization' => 'OAuth '.$token])
            ->attach('file', $csv, 'conversions.csv')
            ->post('https://api-metrika.yandex.net/management/v1/counter/'
                .config('analytics.metrika.counter_id').'/offline_conversions/upload?client_id_type=CLIENT_ID');

        return $response->successful() ? ['sent', null] : ['failed', 'http_'.$response->status()];
    }

    private function finish(AnalyticsOutbox $row, string $status, ?string $reason, bool $attempted = false): void
    {
        $row->forceFill([
            'status' => $status,
            'reason' => $reason,
            'attempts' => $row->attempts + ($attempted || $status === 'sent' ? 1 : 0),
            'sent_at' => $status === 'sent' ? now() : $row->sent_at,
        ])->save();
    }
}
