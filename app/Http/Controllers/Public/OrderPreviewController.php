<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\OrderBridge\PreviewAccess;
use Illuminate\Http\Request;

class OrderPreviewController extends Controller
{
    public function enter(Request $request, string $key)
    {
        if (! PreviewAccess::keyMatches($key)) {
            abort(404);
        }

        return redirect('/cart')
            ->withCookie(PreviewAccess::grant($request))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function leave()
    {
        return redirect('/')->withCookie(PreviewAccess::revoke());
    }
}
