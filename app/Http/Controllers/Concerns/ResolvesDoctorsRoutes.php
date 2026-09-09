<?php

namespace App\Http\Controllers\Concerns;

use App\Services\DetectSite;

trait ResolvesDoctorsRoutes
{
    protected function detectSite(): DetectSite
    {
        return DetectSite::make();
    }

    protected function doctorsPath(string $path = '/'): string
    {
        return $this->detectSite()->doctorsUrl($path);
    }
}
