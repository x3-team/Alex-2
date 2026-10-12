<?php

namespace App\Support\Analytics;

use App\Models\AnalyticsOutbox;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Факт «заявка принята» (lead_accepted по ТЗ): пишется в одной транзакции с заявкой,
 * по строке на платформу. Наружу уходят только form_id и lead_type — без контактов,
 * ответов квиза и id заявки. Решение об отправке фиксируется сразу: разрешён ли экспорт
 * этой формы, дал ли посетитель согласие на аналитику, есть ли идентификатор браузера.
 */
class AcceptedLead
{
    public const EVENT = 'lead_accepted';

    public const CONSENT_COOKIE = 'alex_analytics_consent';

    /** @return AnalyticsOutbox[] */
    public static function record(Order $order, Request $request, string $formId): array
    {
        $eventId = (string) Str::uuid();
        $consent = self::consent($request);
        $exportAllowed = (bool) config('analytics.export.'.$formId, false);
        $params = ['form_id' => $formId, 'lead_type' => 'consumer', 'event_origin' => 'backend'];

        $rows = [];
        foreach ([
            'ga4' => self::gaClientId($request),
            'metrika' => self::ymClientId($request),
        ] as $platform => $clientId) {
            $reason = match (true) {
                ! $exportAllowed => 'export_disabled',
                $consent !== 'granted' => 'no_consent',
                $clientId === null => 'no_client_id',
                default => null,
            };

            $rows[] = AnalyticsOutbox::create([
                'event_id' => $eventId,
                'event_name' => self::EVENT,
                'platform' => $platform,
                'inquiry_ref' => 'site_order:'.$order->id,
                'order_id' => $order->id,
                'params' => $params,
                'client_id' => $reason === null ? $clientId : null,
                'consent' => $consent,
                'status' => $reason === null ? 'pending' : 'suppressed',
                'reason' => $reason,
                'occurred_at' => now(),
            ]);
        }

        return $rows;
    }

    // Значение ставит баннер в app.blade.php: «1:granted» или «1:denied».
    public static function consent(Request $request): ?string
    {
        $value = (string) $request->cookie(self::CONSENT_COOKIE, '');

        return preg_match('/^1:(granted|denied)$/', $value, $m) ? $m[1] : null;
    }

    // _ga = GA1.1.<random>.<timestamp> → client_id <random>.<timestamp>
    public static function gaClientId(Request $request): ?string
    {
        $value = (string) $request->cookie('_ga', '');

        return preg_match('/^GA\d\.\d+\.(\d+\.\d+)$/', $value, $m) ? $m[1] : null;
    }

    public static function ymClientId(Request $request): ?string
    {
        $value = (string) $request->cookie('_ym_uid', '');

        return preg_match('/^\d{6,32}$/', $value) ? $value : null;
    }
}
