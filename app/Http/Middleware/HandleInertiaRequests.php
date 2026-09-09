<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\SharesDoctorsSite;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * MERGE ONLY — production already has HandleInertiaRequests.
 *
 * Do not copy this file over VPS. Add `use SharesDoctorsSite;` and merge
 * `$this->doctorsSiteShare($request)` into the existing share() array.
 * Replacing this class drops Ziggy, flash, and patient auth props.
 */
class HandleInertiaRequests extends Middleware
{
    use SharesDoctorsSite;

    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), $this->doctorsSiteShare($request));
    }
}
