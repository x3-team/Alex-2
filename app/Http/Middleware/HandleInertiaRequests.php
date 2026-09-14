<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\SharesDoctorsSite;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    use SharesDoctorsSite;

    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        if (file_exists($manifest = public_path('build/manifest.json'))) {
            return md5_file($manifest);
        }

        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Public pages: do not leak the admin route map via @routes / Ziggy.
        if (! $request->is('admin', 'admin/*')) {
            config([
                'ziggy.except' => array_merge((array) config('ziggy.except', []), ['admin.*']),
            ]);
        }

        return array_merge(parent::share($request), [
            // Замыкание fn() заставляет Laravel брать СВЕЖИЙ токен текущей сессии
            'csrf_token' => fn () => csrf_token(),
            'auth' => [
                'user' => $request->user(),
            ],
        ], $this->doctorsSiteShare($request));
    }
}
