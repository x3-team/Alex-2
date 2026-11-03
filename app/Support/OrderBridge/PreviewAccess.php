<?php

namespace App\Support\OrderBridge;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Скрытый доступ к новой корзине на проде: по ссылке /cart/preview/{ORDER_BRIDGE_PREVIEW_KEY}
 * браузер получает cookie до закрытия, и только в нём связка работает как при ORDER_BRIDGE_ENABLED=true.
 * В cookie лежит HMAC от ключа, не сам ключ: смена ключа в .env сразу закрывает все выданные доступы.
 * Заказы из такого браузера помечаются тестовыми (is_test) и уходят в Medmis с пометкой TEST.
 */
class PreviewAccess
{
    public const COOKIE = 'alex_order_preview';

    private const MIN_KEY_LENGTH = 32;

    public static function configured(): bool
    {
        return strlen(self::key()) >= self::MIN_KEY_LENGTH;
    }

    public static function keyMatches(string $given): bool
    {
        return self::configured() && hash_equals(self::key(), $given);
    }

    public static function active(?Request $request = null): bool
    {
        if (! self::configured()) {
            return false;
        }

        $value = ($request ?? request())->cookies->get(self::COOKIE);

        return is_string($value) && hash_equals(self::token(), $value);
    }

    public static function grant(Request $request): Cookie
    {
        // Время жизни 0 — cookie до закрытия браузера.
        return cookie(self::COOKIE, self::token(), 0, '/', null, $request->isSecure(), true, false, 'lax');
    }

    public static function revoke(): Cookie
    {
        return cookie()->forget(self::COOKIE);
    }

    private static function token(): string
    {
        return hash_hmac('sha256', 'order-bridge-preview', self::key());
    }

    private static function key(): string
    {
        return trim((string) config('order_bridge.preview_key'));
    }
}
