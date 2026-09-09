<?php

namespace App\Http\Middleware;

use App\Services\DetectSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep doctor sessions off the patient dashboard / Breeze /login home.
 * Do not attach to /admin — a doctor who is also an admin must keep admin access.
 */
class RedirectDoctorFromPatientArea
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! ($user->is_doctor ?? false)) {
            return $next($request);
        }

        $detect = DetectSite::make($request);

        if ($detect->isDoctorsSite()) {
            return $next($request);
        }

        return redirect($detect->doctorsUrl('/cabinet'));
    }
}
