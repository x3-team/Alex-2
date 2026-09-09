<?php

namespace App\Http\Middleware;

use App\Services\DetectSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDoctor
{
    public function handle(Request $request, Closure $next): Response
    {
        $detect = DetectSite::make($request);

        if (! $detect->isDoctorsSite()) {
            abort(404);
        }

        $user = $request->user();

        if (! $user || ! $user->is_doctor) {
            $loginPath = $detect->doctorsUrl('/login');

            return redirect()->guest($loginPath);
        }

        return $next($request);
    }
}
