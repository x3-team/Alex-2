<?php

namespace App\Support\Ssr;

/**
 * Что случилось с SSR на текущем запросе. Читается в InertiaSsrGuard (заголовок X-SSR)
 * и в app.blade.php (запасной текст внутри #app).
 */
class SsrStatus
{
    public const ON = 'on';

    public const OFF = 'off';

    public const SKIP = 'skip';

    private ?string $state = null;

    private ?string $reason = null;

    public function mark(string $state, ?string $reason = null): void
    {
        $this->state = $state;
        $this->reason = $reason;
    }

    public function state(): ?string
    {
        return $this->state;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    /** SSR должен был отрисовать страницу, но не отрисовал. */
    public function failed(): bool
    {
        return $this->state === self::OFF;
    }
}
