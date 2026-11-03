<?php

namespace App\Support\OrderBridge;

class BridgeConfig
{
    // Для текущего запроса: флаг в .env или браузер со скрытым доступом (PreviewAccess).
    public static function enabled(): bool
    {
        return self::globallyEnabled() || PreviewAccess::active();
    }

    public static function globallyEnabled(): bool
    {
        return (bool) config('order_bridge.enabled');
    }

    // Очередь, опрос, справочники и вебхук ЮKassa: у них нет cookie браузера. При выключенном флаге
    // заказы создаются только по скрытой ссылке, поэтому работать с ними можно, если ключ задан.
    public static function backgroundEnabled(): bool
    {
        return self::globallyEnabled() || PreviewAccess::configured();
    }

    public static function outboundReady(): bool
    {
        foreach ([
            'order_bridge.medmis.base_url',
            'order_bridge.medmis.login',
            'order_bridge.medmis.password',
            'order_bridge.medmis.system_name',
            'order_bridge.medmis.branch_id',
            'order_bridge.medmis.schedule_id',
            'order_bridge.medmis.employee_specialization_id',
            'order_bridge.medmis.service_test_id',
            'order_bridge.medmis.service_blood_id',
            'order_bridge.dnkom.url',
            'order_bridge.dnkom.sender_paid',
            'order_bridge.dnkom.sender_unpaid',
            'order_bridge.dnkom.receiver',
            'order_bridge.dnkom.password',
            'order_bridge.dnkom.code_test',
            'order_bridge.dnkom.code_blood',
        ] as $key) {
            if (! self::filled($key)) {
                return false;
            }
        }

        return true;
    }

    public static function paymentReady(): bool
    {
        return self::filled('order_bridge.yookassa.shop_id')
            && self::filled('order_bridge.yookassa.secret_key');
    }

    private static function filled(string $key): bool
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '';
    }
}
