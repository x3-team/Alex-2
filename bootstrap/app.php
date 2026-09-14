<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SetNoindexRobots::class);

        $middleware->web(prepend: [
            \App\Http\Middleware\DetectSiteMiddleware::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\HandleRedirects::class,
            \App\Http\Middleware\SetPublicCacheControl::class,
        ]);

        // Guests on /doctors* (or future doc.* host) go to doctors login, not patient /login.
        $middleware->redirectGuestsTo(function () {
            $request = request();
            $onDoctors = $request->is('doctors', 'doctors/*')
                || str_starts_with(strtolower($request->getHost()), 'doc.');

            if ($onDoctors) {
                if (\Illuminate\Support\Facades\Route::has('doctors.path.login')) {
                    return route('doctors.path.login');
                }
                if (\Illuminate\Support\Facades\Route::has('doctors.login')) {
                    return route('doctors.login');
                }
            }

            return route('login');
        });

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'detect.site' => \App\Http\Middleware\DetectSiteMiddleware::class,
            'doctor' => \App\Http\Middleware\EnsureDoctor::class,
            'doctor.not-patient' => \App\Http\Middleware\RedirectDoctorFromPatientArea::class,
        ]);

        $middleware->encryptCookies(except: [
            'XSRF-TOKEN',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();