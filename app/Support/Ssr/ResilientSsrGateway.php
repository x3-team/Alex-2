<?php

namespace App\Support\Ssr;

use Exception;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\Response;

/**
 * Тот же HTTP-шлюз Inertia, но:
 * - с коротким таймаутом, чтобы зависший Node не держал страницу 30 секунд;
 * - отказ SSR на публичной странице пишется в storage/logs/ssr-fallback-*.log
 *   и виден в заголовке X-SSR: off. Раньше пакет молча отдавал пустой #app.
 */
class ResilientSsrGateway extends HttpGateway
{
    public function dispatch(array $page): ?Response
    {
        $status = app(SsrStatus::class);

        if (! $this->ssrIsEnabled()) {
            $status->mark(SsrStatus::SKIP, 'disabled');

            return null;
        }

        if (! $this->shouldDispatch()) {
            return $this->fail($status, $page, 'bundle missing');
        }

        try {
            $response = Http::connectTimeout(1)
                ->timeout((float) config('inertia.ssr.timeout', 4))
                ->post($this->getUrl('/render'), $page)
                ->throw()
                ->json();
        } catch (Exception $e) {
            if ($e instanceof StrayRequestException) {
                throw $e;
            }

            return $this->fail($status, $page, class_basename($e).': '.mb_substr($e->getMessage(), 0, 300));
        }

        if (! is_array($response) || ! isset($response['body']) || $response['body'] === '') {
            return $this->fail($status, $page, 'empty render');
        }

        $status->mark(SsrStatus::ON);

        return new Response(
            implode("\n", (array) ($response['head'] ?? [])),
            $response['body']
        );
    }

    private function fail(SsrStatus $status, array $page, string $reason): ?Response
    {
        $status->mark(SsrStatus::OFF, $reason);

        try {
            Log::channel('ssr')->warning('SSR fallback', [
                'url' => $page['url'] ?? null,
                'component' => $page['component'] ?? null,
                'reason' => $reason,
                'ua' => mb_substr((string) request()->userAgent(), 0, 200),
            ]);
        } catch (\Throwable) {
            // Лог не должен ронять страницу.
        }

        return null;
    }
}
