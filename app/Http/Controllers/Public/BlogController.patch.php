<?php

/**
 * Apply to the existing public BlogController (index/show/sitemap).
 *
 * - Add ->forCurrentSite() (or ->forAudience(DetectSite::make()->audience()))
 *   to every public blog listing/detail query.
 * - Sitemap: emit only posts matching current site audience from Host/path.
 */

namespace App\Http\Controllers\Public;

use App\Services\DetectSite;

// Example inside index():
//
// Blog::query()
//     ->forCurrentSite()
//     ->where('is_active', true)
//     ...

// Example inside sitemap():
//
// $audience = DetectSite::make()->audience();
// Blog::query()->forAudience($audience)->...
