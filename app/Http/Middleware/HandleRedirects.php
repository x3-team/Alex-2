<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (str_starts_with($request->path(), 'admin') ||
            str_starts_with($request->path(), 'api') ||
            str_starts_with($request->path(), 'sanctum')) {
            return $next($request);
        }

        $redirect = Redirect::findByUrl($request->path());

        if ($redirect) {
            $redirect->incrementHits();
            $final = Redirect::flattenToUrl((string) $redirect->to_url, (string) $redirect->from_url);
            if ($final !== $redirect->to_url) {
                $redirect->to_url = $final;
                $redirect->save();
            }
            return redirect($final, $redirect->status_code);
        }

        return $next($request);
    }
}
