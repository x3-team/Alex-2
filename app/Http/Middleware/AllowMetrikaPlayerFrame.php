<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Плеер Вебвизора и карты кликов Метрики открывают страницу сайта во фрейме на своём
 * домене. nginx отдаёт X-Frame-Options: SAMEORIGIN, и записи показывались без стилей.
 * frame-ancestors в CSP главнее X-Frame-Options: браузер берёт его и игнорирует старый
 * заголовок, поэтому nginx не трогаем. Кроме frame-ancestors, других директив CSP нет.
 */
class AllowMetrikaPlayerFrame
{
    // https://yandex.com/support/metrica/ru/code/install-counter-csp — «Адреса для frame-ancestors».
    public const METRIKA_HOSTS = [
        'metrika.yandex.ru',
        'analytics.yandex.by',
        'analytics.yandex.com',
        'analytics.yandex.com.tr',
        'analytics.yandex.kz',
        'analytics.yandex.ru',
        'metr.yandex.by',
        'metr.yandex.com',
        'metr.yandex.com.tr',
        'metr.yandex.kz',
        'metr.yandex.ru',
        'metrica.ya.ru',
        'metrica.yandex',
        'metrica.yandex.by',
        'metrica.yandex.com',
        'metrica.yandex.com.tr',
        'metrica.yandex.kz',
        'metrica.yandex.ru',
        'metrika.ya.ru',
        'metrika.yandex',
        'metrika.yandex.by',
        'metrika.yandex.com',
        'metrika.yandex.com.tr',
        'metrika.yandex.kz',
        'metrika.yandex.uz',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $response->headers->has('Content-Security-Policy')) {
            $hosts = array_map(fn (string $host) => 'https://'.$host, self::METRIKA_HOSTS);
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self' ".implode(' ', $hosts));
        }

        return $response;
    }
}
