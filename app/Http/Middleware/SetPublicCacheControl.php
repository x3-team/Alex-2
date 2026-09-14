<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPublicCacheControl
{
    /**
     * Session cookie is set for every visitor, so HTML must never be stored in a shared/CDN cache.
     * Public GET: allow the browser a short private reuse. Auth/cart/admin/login: no-store.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $sensitive = $this->isSensitive($request);

        if ($sensitive) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->remove('ETag');

            return $response;
        }

        // Anonymous public GET/HEAD only. Not public/shared: Set-Cookie session is always present.
        $response->headers->set('Cache-Control', 'private, max-age=60, must-revalidate');

        return $response;
    }

    private function isSensitive(Request $request): bool
    {
        if (! $request->isMethodCacheable()) {
            return true;
        }

        if ($request->user()) {
            return true;
        }

        return $request->is([
            'login',
            'register',
            'logout',
            'dashboard',
            'cart',
            'cart/*',
            'admin',
            'admin/*',
            'profile',
            'profile/*',
            'recover',
            'patient/login',
            'forgot-password',
            'reset-password',
            'reset-password/*',
            'confirm-password',
            'verify-email',
            'verify-email/*',
            'email/*',
            'appointment/*',
            'password',
            'password/*',
        ]);
    }
}
