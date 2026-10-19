<?php

namespace App\Http\Middleware;

use App\Support\Ssr\SsrStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Админка, вход и личные разделы рендерятся без SSR: роботам они закрыты, а их
 * маршрутов нет в статичном resources/js/ziggy.js, из-за чего SSR сыпал ошибками Ziggy.
 * На ответ с полной HTML-страницей ставит X-SSR: on | off | skip.
 */
class InertiaSsrGuard
{
    public const PRIVATE_PATHS = [
        'admin', 'admin/*',
        'login', 'register', 'logout',
        'forgot-password', 'reset-password', 'reset-password/*',
        'confirm-password', 'verify-email', 'verify-email/*', 'email/*',
        'password', 'password/*',
        'dashboard', 'dashboard/*',
        'profile', 'profile/*',
        'cart', 'cart/*',
        'recover', 'patient', 'patient/*',
        'cabinet', 'cabinet/*',
        'doctors/cabinet', 'doctors/cabinet/*',
        'doctors/login', 'doctors/register',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(self::PRIVATE_PATHS)) {
            config(['inertia.ssr.enabled' => false]);
        }

        /** @var Response $response */
        $response = $next($request);

        $state = app(SsrStatus::class)->state();
        if ($state !== null && ! $request->header('X-Inertia')) {
            $response->headers->set('X-SSR', $state);
        }

        return $response;
    }
}
