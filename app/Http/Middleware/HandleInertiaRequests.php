<?php

namespace App\Http\Middleware;

use App\Services\DetectSite;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $detect = DetectSite::make($request);

        return array_merge(parent::share($request), [
            'csrf_token' => csrf_token(),
            'auth' => [
                'user' => $request->user(),
            ],
            'site' => [
                'mode' => $detect->mode(),
                'isDoctorsSite' => $detect->isDoctorsSite(),
                'isDoctorsHost' => $detect->isDoctorsHost(),
                'themeColor' => $detect->themeColor(),
                'routePrefix' => $detect->routePrefix(),
                'audience' => $detect->audience(),
            ],
        ]);
    }
}
