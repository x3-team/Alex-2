<?php

namespace App\Support;

use App\Services\DetectSite;
use Illuminate\Http\Request;

/**
 * Absolute origin for canonical, Open Graph, and JSON-LD on the current site contour.
 */
class SeoOrigin
{
    public function __construct(protected Request $request) {}

    public static function make(?Request $request = null): self
    {
        return new self($request ?? request());
    }

    public function siteBaseUrl(): string
    {
        $detect = DetectSite::make($this->request);

        if ($detect->isDoctorsSite()) {
            if ($detect->isDoctorsHost()) {
                return rtrim($this->request->getSchemeAndHttpHost(), '/');
            }

            $origin = rtrim($detect->doctorsOrigin(), '/');
            if ($origin !== '') {
                return $origin;
            }
        }

        return $this->patientBaseUrl();
    }

    public function patientBaseUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
