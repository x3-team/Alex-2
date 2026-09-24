<?php

namespace App\Support;

use App\Models\Blog;
use App\Services\DetectSite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Куда ведёт переключатель аудитории и на каких страницах он виден.
 * Ссылка — та же страница другого домена, если она там есть; иначе главная.
 */
class AudienceSwitchTarget
{
    /** Страницы, которые открываются по тому же пути на обоих доменах. */
    private const SHARED = [
        '/search',
        '/cart',
        '/quiz',
        '/demo-result',
        '/consent',
        '/alex-lab',
        '/alex-lab/licenses',
        '/alex-lab/doctors',
        '/alex-lab/contacts',
        '/alex-lab/privacy',
    ];

    /** Служебные адреса: админка, вход, кабинет, 404 сюда не попадает (отдельное приложение). */
    private const HIDDEN = [
        'admin',
        'login',
        'forgot-password',
        'reset-password',
        'verify-email',
        'confirm-password',
        'patient',
        'recover',
        'dashboard',
        'profile',
        'register',
        'cabinet',
        'up',
    ];

    /** Query, которые безопасно перенести на ту же страницу другого домена. */
    private const QUERY = ['q', 'query', 'search', 'page', 'tags', 'category', 'type', 'allergen'];

    /**
     * @param  callable(string, string): bool|null  $articleFor  audience, slug → published for that audience
     * @return array{visible: bool, patient: string, doctor: string}
     */
    public function share(Request $request, ?callable $articleFor = null): array
    {
        $detect = DetectSite::make($request);
        $logical = $this->logicalPath($request, $detect);

        if ($this->isHidden($logical)) {
            return ['visible' => false, 'patient' => '', 'doctor' => ''];
        }

        $articleFor ??= fn (string $audience, string $slug): bool => $this->articleExists($audience, $slug);
        $query = $this->safeQuery($request);

        return [
            'visible' => true,
            'patient' => $this->href('patients', self::pathFor($logical, 'patients', $articleFor), $query, $detect),
            'doctor' => $this->href('doctors', self::pathFor($logical, 'doctors', $articleFor), $query, $detect),
        ];
    }

    public function isHidden(string $logicalPath): bool
    {
        $path = trim($logicalPath, '/');

        if ($path === 'sitemap.xml' || $path === 'robots.txt') {
            return true;
        }

        foreach (self::HIDDEN as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Путь на сайте целевой аудитории. null из внутренних правил значит «главная».
     *
     * @param  callable(string, string): bool  $articleFor
     */
    public static function pathFor(string $logicalPath, string $targetAudience, callable $articleFor): string
    {
        $path = '/'.trim($logicalPath, '/');
        if ($path === '//') {
            $path = '/';
        }

        if (in_array($path, self::SHARED, true)) {
            return $path;
        }

        if ($targetAudience === DetectSite::MODE_DOCTORS) {
            return self::forDoctors($path, $articleFor) ?? '/';
        }

        return self::forPatients($path, $articleFor) ?? '/';
    }

    /**
     * @param  callable(string, string): bool  $articleFor
     */
    private static function forDoctors(string $path, callable $articleFor): ?string
    {
        if ($path === '/' || str_starts_with($path, '/materials') || str_starts_with($path, '/video')) {
            return $path;
        }

        if ($path === '/doctor-materials') {
            return '/materials';
        }

        if (preg_match('#^/blog/([^/]+)$#', $path, $matches) === 1) {
            $slug = $matches[1];
            if (! in_array($slug, ['authors', 'author'], true) && $articleFor(DetectSite::MODE_DOCTORS, $slug)) {
                return '/materials/'.$slug;
            }

            return null;
        }

        if (str_starts_with($path, '/blog')) {
            return null;
        }

        return $path;
    }

    /**
     * @param  callable(string, string): bool  $articleFor
     */
    private static function forPatients(string $path, callable $articleFor): ?string
    {
        if ($path === '/' || str_starts_with($path, '/blog')) {
            return $path;
        }

        if (preg_match('#^/materials/([^/]+)$#', $path, $matches) === 1 && $matches[1] !== 'documents') {
            return $articleFor(DetectSite::MODE_PATIENTS, $matches[1])
                ? '/blog/'.$matches[1]
                : null;
        }

        if (
            $path === '/doctor-materials'
            || str_starts_with($path, '/materials')
            || str_starts_with($path, '/video')
        ) {
            return null;
        }

        return $path;
    }

    public function logicalPath(Request $request, DetectSite $detect): string
    {
        if ($detect->isDoctorsPath()) {
            $stripped = DoctorsRedirect::stripPathPrefix(
                $request->path(),
                (string) config('doctors.path_prefix', 'doctors'),
            );

            return $stripped ?? '/';
        }

        $path = '/'.trim($request->path(), '/');

        return $path === '//' ? '/' : $path;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function href(string $audience, string $path, array $query, DetectSite $detect): string
    {
        $path = $path === '' ? '/' : $path;
        if ($path === '/') {
            $query = [];
        }

        $suffix = $query === [] ? '' : '?'.http_build_query($query);
        $subdomain = $detect->usesDoctorsSubdomain();
        $prefix = trim((string) config('doctors.path_prefix', 'doctors'), '/');

        if (! $subdomain) {
            if ($audience === DetectSite::MODE_DOCTORS && $this->isDoctorPath($path)) {
                return '/'.$prefix.($path === '/' ? '' : $path).$suffix;
            }

            return ($path === '/' ? '/' : $path).$suffix;
        }

        $doctorsOrigin = rtrim($detect->doctorsOrigin(), '/');
        $origin = $audience === DetectSite::MODE_DOCTORS
            ? $doctorsOrigin
            : (string) preg_replace('#://doc\.#i', '://', $doctorsOrigin);

        return $origin.($path === '/' ? '/' : $path).$suffix;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeQuery(Request $request): array
    {
        $query = [];
        foreach (self::QUERY as $key) {
            if (! $request->query->has($key)) {
                continue;
            }
            $value = $request->query($key);
            if (is_scalar($value) && $value !== '') {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    private function isDoctorPath(string $path): bool
    {
        return $path === '/'
            || str_starts_with($path, '/materials')
            || str_starts_with($path, '/video');
    }

    private function articleExists(string $audience, string $slug): bool
    {
        if (! Schema::hasTable('blogs') || ! Schema::hasColumn('blogs', 'audience')) {
            return false;
        }

        return Blog::query()
            ->forAudience($audience)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->exists();
    }
}
