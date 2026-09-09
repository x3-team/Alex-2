<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class DoctorsRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->routes(function () {
            /** @var callable(string): void $registerDoctorsRoutes */
            $registerDoctorsRoutes = require base_path('routes/doctors.php');

            $doctorsHost = strtolower((string) config('doctors.host'));
            $prefix = trim((string) config('doctors.path_prefix', 'doctors'), '/');
            $apexHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

            // Never bind doctors routes to the patient apex host — that would
            // steal /login and /register from the patient site.
            if ($doctorsHost !== '' && $doctorsHost !== $apexHost) {
                Route::middleware(['web', 'detect.site'])
                    ->domain($doctorsHost)
                    ->group(function () use ($registerDoctorsRoutes) {
                        $registerDoctorsRoutes('doctors.');
                    });
            }

            if (config('doctors.path_preview')) {
                Route::middleware(['web', 'detect.site'])
                    ->prefix($prefix)
                    ->group(function () use ($registerDoctorsRoutes) {
                        $registerDoctorsRoutes('doctors.path.');
                    });
            }
        });
    }
}
