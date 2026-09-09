<?php

namespace App\Support;

use App\Services\DetectSite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DoctorsRedirect
{
    public static function maybeRedirectToSubdomain(Request $request): ?Response
    {
        if (! config('doctors.subdomain_redirect')) {
            return null;
        }

        $detect = DetectSite::make($request);

        if ($detect->isDoctorsHost() || ! $detect->isDoctorsPath()) {
            return null;
        }

        $doctorsHost = $detect->configuredDoctorsHost();
        $prefix = trim((string) config('doctors.path_prefix', 'doctors'), '/');
        $path = $request->path();

        if ($path === $prefix) {
            $targetPath = '/';
        } elseif (str_starts_with($path, $prefix.'/')) {
            $targetPath = '/'.substr($path, strlen($prefix) + 1);
        } else {
            return null;
        }

        $query = $request->getQueryString();
        $url = $request->getScheme().'://'.$doctorsHost.$targetPath.($query ? '?'.$query : '');

        return redirect()->away($url, 301);
    }
}
