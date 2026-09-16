<?php

namespace App\Http\Middleware;

use App\Services\DetectSite;
use App\Support\DoctorsRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectSiteMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($redirect = DoctorsRedirect::maybeRedirectToSubdomain($request)) {
            return $redirect;
        }

        app()->instance(DetectSite::class, DetectSite::make($request));

        return $next($request);
    }
}
