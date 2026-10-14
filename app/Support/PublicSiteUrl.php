<?php

namespace App\Support;

use App\Models\Blog;

/**
 * Абсолютные ссылки «на сайт» из админки. Админка живёт на одном домене,
 * а материал открывается на сайте своей аудитории:
 * пациенты — APP_URL/blog/{slug}, врачи — https://doc.alexallergotest.ru/materials/{slug}.
 */
class PublicSiteUrl
{
    public static function patientOrigin(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function doctorsOrigin(): string
    {
        $host = strtolower(trim((string) config('doctors.host', 'doc.alexallergotest.ru')));
        if ($host === '') {
            return '';
        }

        $scheme = parse_url(self::patientOrigin(), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.$host;
    }

    public static function doctors(string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');
        $origin = self::doctorsOrigin();

        if ($origin === '') {
            $prefix = trim((string) config('doctors.path_prefix', 'doctors'), '/');

            return self::patientOrigin().'/'.$prefix.($path === '/' ? '' : $path);
        }

        return $origin.$path;
    }

    public static function patients(string $path = '/'): string
    {
        return self::patientOrigin().'/'.ltrim($path, '/');
    }

    public static function blog(Blog $blog): string
    {
        $slug = (string) $blog->slug;

        if ($blog->isForDoctors()) {
            // Статьи врачей открываются как /materials/{slug} (см. sitemap врачей).
            return self::doctors('/materials/'.$slug);
        }

        return self::patients('/blog/'.$slug);
    }
}
