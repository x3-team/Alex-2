<?php

namespace App\Support;

use App\Services\DetectSite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DoctorsRedirect
{
    public static function stripPathPrefix(string $path, string $prefix): ?string
    {
        $prefix = trim($prefix, '/');
        $path = trim($path, '/');

        if ($path === $prefix) {
            return '/';
        }

        if (str_starts_with($path, $prefix.'/')) {
            return '/'.substr($path, strlen($prefix) + 1);
        }

        return null;
    }

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
        $targetPath = self::stripPathPrefix($request->path(), $prefix);

        if ($targetPath === null) {
            return null;
        }

        $query = $request->getQueryString();
        $url = $request->getScheme().'://'.$doctorsHost.$targetPath.($query ? '?'.$query : '');

        return redirect()->away($url, 301);
    }
}
