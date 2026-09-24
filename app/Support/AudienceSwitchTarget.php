<?php

namespace App\Support;

use App\Models\Blog;
use App\Models\Category;
use App\Services\DetectSite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Куда ведёт переключатель аудитории и на каких страницах он виден.
 * Ссылка — та же страница, если она открывается у другой аудитории,
 * иначе ближайший раздел (список блога). Главная — только если аналога нет.
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
     * @param  callable(string): bool|null  $categoryFor  slug → категория блога существует
     * @return array{visible: bool, patient: string, doctor: string}
     */
    public function share(Request $request, ?callable $articleFor = null, ?callable $categoryFor = null): array
    {
        $detect = DetectSite::make($request);
        $logical = $this->logicalPath($request, $detect);

        if ($this->isHidden($logical)) {
            return ['visible' => false, 'patient' => '', 'doctor' => ''];
        }

        $articleFor ??= fn (string $audience, string $slug): bool => $this->articleExists($audience, $slug);
        $categoryFor ??= fn (string $slug): bool => $this->categoryExists($slug);
        $query = $this->safeQuery($request);

        return [
            'visible' => true,
            'patient' => $this->href('patients', self::pathFor($logical, 'patients', $articleFor, $categoryFor), $query, $detect),
            'doctor' => $this->href('doctors', self::pathFor($logical, 'doctors', $articleFor, $categoryFor), $query, $detect),
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
     * @param  callable(string): bool|null  $categoryFor
     */
    public static function pathFor(
        string $logicalPath,
        string $targetAudience,
        callable $articleFor,
        ?callable $categoryFor = null,
    ): string {
        $path = '/'.trim($logicalPath, '/');
        if ($path === '//') {
            $path = '/';
        }

        if (in_array($path, self::SHARED, true)) {
            return $path;
        }

        $categoryFor ??= fn (string $slug): bool => false;

        if ($targetAudience === DetectSite::MODE_DOCTORS) {
            return self::forDoctors($path, $articleFor) ?? '/';
        }

        return self::forPatients($path, $articleFor, $categoryFor) ?? '/';
    }

    /**
     * Фильтр ленты врачей (`type`) имеет смысл только на /materials.
     * На главной query не переносится.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public static function queryFor(string $path, array $query): array
    {
        if ($path === '/' || $path === '') {
            return [];
        }

        if (! str_starts_with($path, '/materials')) {
            unset($query['type']);
        }

        return $query;
    }

    /**
     * Список статей врачей — /materials (страница «Блог про аллергию»).
     * /blog/{категория} на врачебном домене не остаётся: контроллер отдаёт 301 на /materials.
     *
     * @param  callable(string, string): bool  $articleFor
     */
    private static function forDoctors(string $path, callable $articleFor): ?string
    {
        if ($path === '/' || str_starts_with($path, '/materials') || str_starts_with($path, '/video')) {
            return $path;
        }

        if ($path === '/doctor-materials' || $path === '/blog') {
            return '/materials';
        }

        if ($path === '/blog/authors' || preg_match('#^/blog/author/([^/]+)$#', $path) === 1) {
            return $path;
        }

        if (preg_match('#^/blog/(.+)$#', $path, $matches) === 1) {
            $slug = $matches[1];
            // Маршрут /materials/{category} — один сегмент. Слаг со слэшем там не откроется.
            if (! str_contains($slug, '/') && $articleFor(DetectSite::MODE_DOCTORS, $slug)) {
                return '/materials/'.$slug;
            }

            return '/materials';
        }

        return $path;
    }

    /**
     * @param  callable(string, string): bool  $articleFor
     * @param  callable(string): bool  $categoryFor
     */
    private static function forPatients(string $path, callable $articleFor, callable $categoryFor): ?string
    {
        if ($path === '/' || str_starts_with($path, '/blog')) {
            return $path;
        }

        if ($path === '/doctor-materials' || $path === '/materials' || $path === '/materials/documents') {
            return '/blog';
        }

        if (preg_match('#^/materials/([^/]+)$#', $path, $matches) === 1) {
            $slug = $matches[1];
            if ($articleFor(DetectSite::MODE_PATIENTS, $slug)) {
                return '/blog/'.$slug;
            }

            if (! str_contains($slug, '/') && $categoryFor($slug)) {
                return '/blog/'.$slug;
            }

            return '/blog';
        }

        if (str_starts_with($path, '/video')) {
            return '/blog';
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
        $query = self::queryFor($path, $query);

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

    private function categoryExists(string $slug): bool
    {
        if ($slug === '' || str_contains($slug, '/') || ! Schema::hasTable('categories')) {
            return false;
        }

        return Category::query()->where('slug', $slug)->exists();
    }
}
