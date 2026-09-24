<?php

namespace App\Http\Middleware\Concerns;

use App\Services\DetectSite;
use App\Support\AudienceSwitchTarget;
use Illuminate\Http\Request;

/**
 * Merge into production HandleInertiaRequests::share() — do not replace that class.
 *
 *     return array_merge(parent::share($request), [
 *         // existing keys...
 *     ], $this->doctorsSiteShare($request));
 */
trait SharesDoctorsSite
{
    protected function doctorsSiteShare(Request $request): array
    {
        $detect = DetectSite::make($request);

        return [
            'site' => $detect->sharePayload() + [
                'switch' => app(AudienceSwitchTarget::class)->share($request),
            ],
        ];
    }
}
