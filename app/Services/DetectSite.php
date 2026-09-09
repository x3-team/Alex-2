<?php

namespace App\Services;

use Illuminate\Http\Request;

class DetectSite
{
    public const MODE_PATIENTS = 'patients';

    public const MODE_DOCTORS = 'doctors';

    public function __construct(protected Request $request)
    {
    }

    public static function make(?Request $request = null): self
    {
        return new self($request ?? request());
    }

    public function host(): string
    {
        return strtolower($this->request->getHost());
    }

    public function configuredDoctorsHost(): string
    {
        return strtolower((string) config('doctors.host'));
    }

    public function isDoctorsHost(): bool
    {
        return $this->host() === $this->configuredDoctorsHost();
    }

    public function isDoctorsPath(): bool
    {
        if (! config('doctors.path_preview')) {
            return false;
        }

        $prefix = trim((string) config('doctors.path_prefix', 'doctors'), '/');

        return $this->request->is($prefix)
            || $this->request->is($prefix.'/*');
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

        if ($this->isDoctorsPath()) {
            return '/'.trim((string) config('doctors.path_prefix', 'doctors'), '/');
        }

        return '';
    }

    public function themeColor(): ?string
    {
        return $this->isDoctorsSite()
            ? (string) config('doctors.theme_color')
            : null;
    }

    public function audience(): string
    {
        return $this->isDoctorsSite()
            ? self::MODE_DOCTORS
            : self::MODE_PATIENTS;
    }

    public function doctorsUrl(string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');

        if ($this->isDoctorsHost()) {
            return $path === '//' ? '/' : $path;
        }

        $prefix = trim((string) config('doctors.path_prefix', 'doctors'), '/');

        return '/'.$prefix.($path === '/' ? '' : $path);
    }
}
