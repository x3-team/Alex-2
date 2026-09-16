<?php

namespace App\Services;

use Illuminate\Http\Request;

class DetectSite
{
    public const MODE_PATIENTS = 'patients';

    public const MODE_DOCTORS = 'doctors';

    public function __construct(
        protected Request $request,
        protected ?array $configOverride = null,
    ) {
    }

    public static function make(?Request $request = null, ?array $configOverride = null): self
    {
        return new self($request ?? request(), $configOverride);
    }

    /**
     * Host is the doctors site when it equals APP_DOCTORS_HOST
     * or matches the doc.* pattern (doc.alexallergotest.ru, doc.local.test).
     */
    public static function hostIsDoctors(string $host, string $configuredHost): bool
    {
        $host = strtolower(trim($host));
        $configuredHost = strtolower(trim($configuredHost));

        if ($host === '') {
            return false;
        }

        if ($configuredHost !== '' && $host === $configuredHost) {
            return true;
        }

        return str_starts_with($host, 'doc.');
    }

    public static function pathIsDoctors(string $path, string $prefix, bool $pathPreview): bool
    {
        if (! $pathPreview) {
            return false;
        }

        $prefix = trim($prefix, '/');
        $path = trim($path, '/');

        return $path === $prefix || str_starts_with($path, $prefix.'/');
    }

    public static function prefixDoctorsUrl(string $path, string $prefix, bool $onDoctorsHost): string
    {
        $path = '/'.ltrim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        if ($onDoctorsHost) {
            return $path === '//' ? '/' : $path;
        }

        $prefix = trim($prefix, '/');

        return '/'.$prefix.($path === '/' ? '' : $path);
    }

    public function host(): string
    {
        return strtolower($this->request->getHost());
    }

    public function configuredDoctorsHost(): string
    {
        return strtolower((string) $this->doctorsConfig('host', 'doc.alexallergotest.ru'));
    }

    public function isDoctorsHost(): bool
    {
        return self::hostIsDoctors($this->host(), $this->configuredDoctorsHost());
    }

    public function isDoctorsPath(): bool
    {
        $prefix = trim((string) $this->doctorsConfig('path_prefix', 'doctors'), '/');

        return self::pathIsDoctors(
            $this->request->path(),
            $prefix,
            (bool) $this->doctorsConfig('path_preview', true),
        );
    }

    public function isDoctorsSite(): bool
    {
        return $this->isDoctorsHost() || $this->isDoctorsPath();
    }

    public function mode(): string
    {
        return $this->isDoctorsSite()
            ? self::MODE_DOCTORS
            : self::MODE_PATIENTS;
    }

    public function routePrefix(): string
    {
        if ($this->isDoctorsHost()) {
            return '';
        }

        if ($this->isDoctorsPath() || $this->doctorsConfig('path_preview', true)) {
            return '/'.trim((string) $this->doctorsConfig('path_prefix', 'doctors'), '/');
        }

        return '';
    }

    public function themeColor(): ?string
    {
        return $this->isDoctorsSite()
            ? (string) $this->doctorsConfig('theme_color', '#cba98e')
            : null;
    }

    public function audience(): string
    {
        return $this->isDoctorsSite()
            ? self::MODE_DOCTORS
            : self::MODE_PATIENTS;
    }

    public function usesDoctorsSubdomain(): bool
    {
        if ($this->doctorsConfig('subdomain_redirect', false)) {
            return true;
        }

        return ! (bool) $this->doctorsConfig('path_preview', true);
    }

    public function doctorsOrigin(): string
    {
        $host = $this->configuredDoctorsHost();
        if ($host === '') {
            return '';
        }

        $scheme = $this->request->secure() || $this->request->header('X-Forwarded-Proto') === 'https'
            ? 'https'
            : ($this->request->getScheme() ?: 'https');

        return $scheme.'://'.$host;
    }

    public function doctorsUrl(string $path = '/'): string
    {
        $relative = self::prefixDoctorsUrl(
            $path,
            (string) $this->doctorsConfig('path_prefix', 'doctors'),
            $this->isDoctorsHost() || $this->usesDoctorsSubdomain(),
        );

        if ($this->isDoctorsHost() || ! $this->usesDoctorsSubdomain()) {
            return $relative;
        }

        $origin = $this->doctorsOrigin();

        return $origin === '' ? $relative : $origin.$relative;
    }

    public function sharePayload(): array
    {
        return [
            'mode' => $this->mode(),
            'isDoctorsSite' => $this->isDoctorsSite(),
            'isDoctorsHost' => $this->isDoctorsHost(),
            'themeColor' => $this->themeColor(),
            'routePrefix' => $this->routePrefix(),
            'audience' => $this->audience(),
            'doctorsOrigin' => $this->usesDoctorsSubdomain() ? $this->doctorsOrigin() : '',
        ];
    }

    protected function doctorsConfig(string $key, mixed $default = null): mixed
    {
        if (is_array($this->configOverride) && array_key_exists($key, $this->configOverride)) {
            return $this->configOverride[$key];
        }

        return config('doctors.'.$key, $default);
    }
}
