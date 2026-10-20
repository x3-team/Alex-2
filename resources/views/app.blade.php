@php
    // Главная заливается фоном своей версии ещё до загрузки CSS, иначе смена
    // домена через переключатель даёт белую вспышку. Цвета те же, что в main.css.
    $isHome = ($page['component'] ?? '') === 'Public/Welcome';
    $audienceColor = \App\Services\DetectSite::make()->isDoctorsSite() ? '#cba98e' : '#cac9bf';
    // Метку ставит только переключатель; при обычном заходе ничего не меняется.
    $cameFromSwitch = request()->query('from') === 'switch';
    // На главной фон версии стоит всегда. На остальных страницах — только в момент перехода,
    // чтобы обычный заход не перекрашивал документ.
    $paintAudience = $isHome || $cameFromSwitch;
    // Inertia при старте записывает page.url в адрес. Убираем метку оттуда,
    // иначе replaceState в разметке перебивается и ?from=switch остаётся в канонике вкладки.
    if ($cameFromSwitch && isset($page['url'])) {
        $switchUrl = parse_url($page['url']) ?: [];
        $switchQuery = [];
        if (! empty($switchUrl['query'])) {
            parse_str($switchUrl['query'], $switchQuery);
            unset($switchQuery['from']);
        }
        $page['url'] = ($switchUrl['path'] ?? '/')
            .($switchQuery ? '?'.http_build_query($switchQuery) : '')
            .(isset($switchUrl['fragment']) ? '#'.$switchUrl['fragment'] : '');
    }
    // Админка и вход в неё: полный Ziggy, без Метрики/GA и без чата Carrot Quest
    // (его подтягивает Метрика). Тот же список путей — в resources/js/utils/adminArea.js.
    $isAdminArea = request()->is('admin', 'admin/*', 'login', 'forgot-password', 'reset-password', 'reset-password/*', 'confirm-password', 'verify-email', 'verify-email/*', 'register');
    // Квиз, поиск аллергенов, личные разделы и скрытая корзина — без Метрики и GA (ТЗ по аналитике, §8).
    // Тот же список — в resources/js/analytics/rules.js.
    $analyticsExcluded = $isAdminArea || request()->is('search', 'search/*', 'cart', 'cart/*', 'dashboard', 'dashboard/*', 'profile', 'profile/*', 'recover', 'recover/*', 'cabinet', 'cabinet/*');
    $htmlClasses = array_filter([
        $cameFromSwitch ? 'audience-switching' : null,
        $isAdminArea ? 'is-admin-area' : null,
    ]);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @if($htmlClasses) class="{{ implode(' ', $htmlClasses) }}" @endif
    @if($paintAudience) style="background-color: {{ $audienceColor }}" @endif
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        @if($paintAudience)
        <meta name="theme-color" content="{{ $audienceColor }}">
        @endif
        @if(($page['component'] ?? '') === 'Public/Welcome')
        <link rel="preload" as="image" type="image/webp" href="/videos/posters/hero-mobile.webp" media="(max-width: 1024px)" fetchpriority="high">
        <link rel="preload" as="image" type="image/webp" href="/videos/posters/hero-desktop.webp" media="(min-width: 1025px)" fetchpriority="high">
        @endif
        <style>
            .page-container { display: block; width: 100%; margin: 0; padding: 0; min-height: 100dvh; overflow: hidden; }
            /* Mac overscroll: never flash white behind home storytelling */
            html:has(.home-page-container), body:has(.home-page-container) { overflow: hidden; overscroll-behavior: none; background-color: #cac9bf; height: 100%; max-height: 100dvh; }
            html:has(.home-page-container.doctor-mode), body:has(.home-page-container.doctor-mode) { background-color: #cba98e; }
            .home-page-container, .home-page-container .home-content { overscroll-behavior: none; }
            /* Critical hero: match poster tone so FCP is not a dark gray void */
            .home-page-container .video-bg-container { background: #cac9bf; opacity: 1; }
            .home-page-container .hero-poster { position: absolute; inset: 0; z-index: 0; margin: 0; pointer-events: none; }
            .home-page-container .hero-poster img { width: 100%; height: 100%; object-fit: cover; display: block; }
            .home-page-container .bg-video { opacity: 0; }
            /* До загрузки бандла чип уже над встречающей заливкой. Специфичность
               выше, чем у .audience-switch--floating { z-index: 110 }. */
            html.audience-switching .audience-switch--floating { z-index: 2147483001; }
            @media (max-width: 1024px) {
                .page-container:not(.home-page-container) { overflow: visible; height: auto; min-height: 100dvh; }
                .home-page-container .site-sidebar { display: none !important; }
                .home-page-container,
                .home-page-container .home-content { background: #cac9bf; }
                .figma-hero-title {
                    position: absolute;
                    left: 16px;
                    right: 16px;
                    bottom: 180px;
                    width: auto;
                    margin: 0;
                    font: 400 32px/1.2 system-ui, -apple-system, 'Roboto', sans-serif;
                }
            }
            @media (min-width: 1025px) {
                .page-container {
                    display: grid;
                    grid-template-columns: clamp(420px, 35.2604167vw, 677px) minmax(0, 1fr);
                    height: 100vh;
                    max-width: 1920px;
                    overflow: hidden;
                    background: #c8ccbe;
                }
                .page-container > .site-sidebar { grid-column: 1; grid-row: 1; }
                .page-container > .home-content,
                .page-container > .content-container,
                .page-container.site-sidebar-layout > .flex-1 { grid-column: 2; grid-row: 1; min-width: 0; }
            }
            /* LCP: paint hero H1 from SSR HTML before app CSS/JS finishes */
            .figma-hero-title {
                opacity: 1 !important;
                transform: none !important;
                animation: none !important;
                color: #000;
            }
            .slide-layer.active .figma-hero-title {
                opacity: 1 !important;
            }
            .slide-layer:not(.active) .figma-hero-title {
                opacity: 0 !important;
            }
            .home-page-container.doctor-mode .figma-hero-title { color: #fff; }
            .home-page-container.doctor-mode,
            .home-page-container.doctor-mode .home-content,
            .home-page-container.doctor-mode .video-bg-container { background: #cba98e; }
            /* Reduce FOUC while full app.css loads non-blocking on Welcome */
            .home-page-container .home-content { position: relative; min-height: 100dvh; overflow: hidden; }
            .home-page-container .slides-deck-container,
            .home-page-container .slide-layer { position: absolute; inset: 0; }
            .home-page-container .slide-layer:not(.active) { visibility: hidden; pointer-events: none; }
            .home-page-container .bg-video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; }
            .home-page-container .bg-video.active { opacity: 1; }
        </style>
        <link rel="icon" href="/favicon.ico?v=20260826x" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v=20260826x">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=20260826x">

      
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <!-- Fonts: non-blocking; home only needs Roboto (system-ui paints first) -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        @if(($page['component'] ?? '') === 'Public/Welcome')
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=optional" rel="stylesheet" media="print" onload="this.media='all'">
        <noscript>
            <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&amp;display=optional" rel="stylesheet" />
        </noscript>
        @else
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
        <noscript>
            <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600&amp;display=swap" rel="stylesheet" />
            <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&amp;display=swap" rel="stylesheet" />
        </noscript>
        @endif
        <!-- Scripts -->
        @if($isAdminArea)
        @routes
        @else
        @routes('public')
        @endif
        <script>window.Ziggy = Ziggy;</script>
        @if(($page['component'] ?? '') === 'Public/Welcome')
        <script>
            /* 1.0.75: minimal cold-open scroll reset only (no --app-height / visualViewport). */
            (function () {
              try { if ('scrollRestoration' in history) history.scrollRestoration = 'manual'; } catch (e) {}
              try { window.scrollTo(0, 0); } catch (e) {}
            })();
        </script>
        @endif
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
        @php
            $seoPath = request()->path();
            $seoProps = $page['props'] ?? [];
            $documentTitle = data_get($seoProps, 'meta.title')
                ?: data_get($seoProps, 'pageTitle')
                ?: data_get($seoProps, 'blogMeta.title')
                ?: data_get($seoProps, 'authorMeta.title')
                ?: data_get($seoProps, 'authorsMeta.title')
                ?: data_get($seoProps, 'blog.seo_title')
                ?: data_get($seoProps, 'blog.title');
            $titleFallbacks = [
                '/' => 'Тест на аллергию ALEX² — 300 аллергенов за один анализ',
                'search' => 'Поиск аллергенов — ALEX²',
                'cart' => 'Корзина — запись на тест ALEX²',
                'alex-lab' => 'Лаборатория ALEX LAB',
                'login' => 'Вход — ALEX LAB',
                'register' => 'Регистрация — ALEX LAB',
                'quiz' => 'Нужно ли мне сдать тест на аллергию?',
                'demo-result' => 'Пример результата теста ALEX²',
                'recover' => 'Восстановление доступа — ALEX LAB',
                'patient/login' => 'Вход в личный кабинет — ALEX LAB',
                'blog' => 'Блог — ALEX LAB',
            ];
            if (!is_string($documentTitle) || trim($documentTitle) === '') {
                $documentTitle = $titleFallbacks[$seoPath] ?? null;
                if (!$documentTitle && str_starts_with($seoPath, 'alex-lab')) {
                    $documentTitle = 'Лаборатория ALEX LAB';
                }
            }
            $noindexPaths = ['cart', 'login', 'register', 'up', 'recover', 'patient/login'];
            $isDoctorsSite = (bool) data_get($seoProps, 'site.isDoctorsSite');
            $blogNoindex = (bool) data_get($seoProps, 'blog.noindex')
                || (bool) data_get($seoProps, 'blogMeta.noindex')
                || (bool) data_get($seoProps, 'videosMeta.noindex')
                || (bool) data_get($seoProps, 'seoMeta.noindex');
            $robotsMeta = ($blogNoindex || in_array($seoPath, $noindexPaths, true))
                ? ($blogNoindex ? 'noindex' : 'noindex, nofollow')
                : null;
            // Поиск аллергенов не индексируем, но ссылки с него робот пусть проходит.
            if (! $robotsMeta && $seoPath === 'search') {
                $robotsMeta = 'noindex, follow';
            }

            $documentDescription = data_get($seoProps, 'meta.description')
                ?: data_get($seoProps, 'blogMeta.description')
                ?: data_get($seoProps, 'authorMeta.description')
                ?: data_get($seoProps, 'authorsMeta.description')
                ?: data_get($seoProps, 'blog.seo_description')
                ?: data_get($seoProps, 'blog.excerpt');
            $documentDescription = is_string($documentDescription) ? trim($documentDescription) : '';
            $descLower = mb_strtolower($documentDescription);
            if (
                $documentDescription === ''
                || $descLower === mb_strtolower(trim((string) $documentTitle))
                || $descLower === 'лаборатория alexlab'
                || $descLower === 'ffffff'
            ) {
                $descriptionFallbacks = [
                    '/' => 'Тест на аллергию ALEX² — исследование почти 300 аллергенов по одному образцу крови.',
                    'quiz' => 'Короткий тест: нужно ли сдавать анализ ALEX² на аллергию.',
                    'blog' => 'Статьи об аллергии, диагностике и жизни с аллергией — ALEX LAB.',
                    'search' => 'Поиск аллергенов теста ALEX².',
                    'alex-lab' => 'Лаборатория ALEX LAB — тест на аллергию ALEX².',
                    'demo-result' => 'Пример результата теста на аллергию ALEX².',
                ];
                if ($isDoctorsSite) {
                    $doctorDescFallbacks = [
                        '/' => 'Информация для врачей об аллергочипе ALEX²: диагностика, материалы для практики.',
                        'materials' => trim((string) data_get($seoProps, 'blogMeta.description'))
                            ?: 'Материалы для врачей: статьи, видео и документы лаборатории ALEX LAB.',
                    ];
                    $documentDescription = $doctorDescFallbacks[$seoPath]
                        ?? (str_starts_with((string) $seoPath, 'materials')
                            ? $doctorDescFallbacks['materials']
                            : 'Материалы и информация для врачей — ALEX².');
                } else {
                    $documentDescription = $descriptionFallbacks[$seoPath]
                        ?? (str_starts_with((string) $seoPath, 'blog')
                            ? $descriptionFallbacks['blog']
                            : 'Лаборатория ALEX LAB — тест на аллергию ALEX².');
                }
            }

            if (is_string($documentTitle)) {
                $documentTitle = str_replace('АLEX', 'ALEX', $documentTitle);
            }

            $alexLabUnique = [
                'alex-lab/licenses' => [
                    'title' => 'Лицензии — ALEX LAB',
                    'description' => 'Лицензии и разрешительные документы лаборатории ALEX LAB.',
                ],
                'alex-lab/contacts' => [
                    'title' => 'Контакты — ALEX LAB',
                    'description' => 'Контакты лаборатории ALEX LAB: адрес, телефон, реквизиты.',
                ],
                'alex-lab/privacy' => [
                    'title' => 'Политика конфиденциальности — ALEX LAB',
                    'description' => 'Политика конфиденциальности лаборатории ALEX LAB.',
                ],
                'alex-lab/doctors' => [
                    'title' => 'Врачи и эксперты — ALEX LAB',
                    'description' => 'Врачи и эксперты лаборатории ALEX LAB.',
                ],
                'alex-lab/consent' => [
                    'title' => 'Согласие на обработку ПД — ALEX LAB',
                    'description' => 'Согласие на обработку персональных данных ALEX LAB.',
                ],
            ];
            if (isset($alexLabUnique[$seoPath])) {
                $documentTitle = $alexLabUnique[$seoPath]['title'];
                $documentDescription = $alexLabUnique[$seoPath]['description'];
            }

            $blog = data_get($seoProps, 'blog');
            $isArticle = is_array($blog) && !empty($blog['slug']) && !empty($blog['title']);

            $siteBase = \App\Support\SeoOrigin::make()->siteBaseUrl();
            $patientBase = rtrim((string) config('app.url'), '/');
            if ($isArticle) {
                $articleCanonical = trim((string) ($blog['canonical_url'] ?? ''));
                if ($articleCanonical !== '') {
                    $canonicalUrl = $articleCanonical;
                } elseif ($isDoctorsSite) {
                    $canonicalUrl = $siteBase.'/materials/'.ltrim((string) $blog['slug'], '/');
                } else {
                    $canonicalUrl = $patientBase.'/blog/'.ltrim((string) $blog['slug'], '/');
                }
            } elseif ($seoPath === '/' || $seoPath === '') {
                $canonicalUrl = $siteBase;
            } else {
                $canonicalUrl = $siteBase.'/'.ltrim((string) $seoPath, '/');
            }

            $pageNum = (int) request()->query('page', 1);
            $canonicalQuery = [];
            if (
                $pageNum > 1
                && ! $isArticle
                && (
                    $seoPath === 'blog'
                    || preg_match('#^blog/authors/[A-Za-z0-9_-]+$#', (string) $seoPath)
                    || (
                        preg_match('#^blog/[A-Za-z0-9_-]+$#', (string) $seoPath)
                        && $seoPath !== 'blog/authors'
                    )
                    || ($isDoctorsSite && (string) $seoPath === 'materials')
                )
            ) {
                $canonicalQuery['page'] = $pageNum;
            }
            if ($isDoctorsSite && (string) $seoPath === 'materials' && ! $isArticle) {
                $materialType = (string) request()->query('type', '');
                if (in_array($materialType, ['articles', 'videos', 'documents'], true)) {
                    $canonicalQuery['type'] = $materialType;
                }
            }
            if ($canonicalQuery !== []) {
                $canonicalUrl .= '?'.http_build_query($canonicalQuery);
            }

            $ogType = $isArticle ? 'article' : 'website';
            $ogTitle = $isArticle
                ? (trim((string) ($blog['og_title'] ?? ''))
                    ?: trim((string) ($blog['seo_title'] ?? ''))
                    ?: trim((string) ($blog['title'] ?? ''))
                    ?: $documentTitle)
                : $documentTitle;
            $ogDescription = $isArticle
                ? (trim((string) ($blog['og_description'] ?? ''))
                    ?: trim((string) ($blog['seo_description'] ?? ''))
                    ?: trim((string) ($blog['excerpt'] ?? ''))
                    ?: $documentDescription)
                : $documentDescription;

            $ogImage = null;
            $ogImageWidth = null;
            $ogImageHeight = null;
            if ($isArticle && !empty($blog['preview_image'])) {
                $ogImage = $siteBase.'/storage/'.ltrim((string) $blog['preview_image'], '/');
                $localImage = public_path('storage/'.ltrim((string) $blog['preview_image'], '/'));
                if (is_file($localImage)) {
                    $size = @getimagesize($localImage);
                    if (is_array($size) && !empty($size[0]) && !empty($size[1])) {
                        $ogImageWidth = $size[0];
                        $ogImageHeight = $size[1];
                    }
                }
            }
            if (!$ogImage) {
                $ogImage = $siteBase.'/og-image.png';
                $ogImageWidth = 1200;
                $ogImageHeight = 630;
            }
        @endphp
        @if(!empty($documentTitle))
        <title>{{ $documentTitle }}</title>
        @endif
        @if(!empty($documentDescription))
        <meta name="description" content="{{ $documentDescription }}">
        @endif
        @if($robotsMeta)
        <meta name="robots" content="{{ $robotsMeta }}">
        @endif
        @if(!empty($canonicalUrl))
        <link rel="canonical" href="{{ $canonicalUrl }}">
        @endif
        @if(!empty($ogTitle))
        <meta property="og:type" content="{{ $ogType }}">
        <meta property="og:title" content="{{ $ogTitle }}">
        <meta property="og:description" content="{{ $ogDescription }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:locale" content="ru_RU">
        <meta property="og:site_name" content="ALEX LAB">
        @if(!empty($ogImage))
        <meta property="og:image" content="{{ $ogImage }}">
        @if(!empty($ogImageWidth) && !empty($ogImageHeight))
        <meta property="og:image:width" content="{{ $ogImageWidth }}">
        <meta property="og:image:height" content="{{ $ogImageHeight }}">
        @endif
        @endif
        <meta name="twitter:card" content="{{ !empty($ogImage) ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $ogTitle }}">
        <meta name="twitter:description" content="{{ $ogDescription }}">
        @if(!empty($ogImage))
        <meta name="twitter:image" content="{{ $ogImage }}">
        @endif
        @endif
        {{-- 🔹 Внедрение JSON-LD на стороне сервера для робота Яндекса --}}
        @if(!empty($page['props']['seoJsonLd']))
            @foreach($page['props']['seoJsonLd'] as $schema)
                <script type="application/ld+json">
                    {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
                </script>
            @endforeach
        @endif


    </head>
    <body class="font-sans antialiased"@if($paintAudience) style="background-color: {{ $audienceColor }}"@endif>
        {{-- SSR article H1 removed in 1.0.57: Inertia SSR already renders visible H1 --}}
        @if($cameFromSwitch)
        {{-- Встречаем тем же цветом, каким уходила прошлая страница, и проявляем контент.
             Разметка и скрипт инлайном: ждать бандл нельзя, иначе будет видно стык. --}}
        <div id="audience-arrival-veil" style="position:fixed;inset:0;z-index:2147483000;pointer-events:none;opacity:1;transition:opacity 360ms ease;background-color:{{ $audienceColor }}"></div>
        <script>
            (function () {
                var veil = document.getElementById('audience-arrival-veil');
                if (!veil) return;
                var url = new URL(window.location.href);
                url.searchParams.delete('from');
                window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);

                var done = function () {
                    document.documentElement.classList.remove('audience-switching');
                    if (veil && veil.parentNode) veil.parentNode.removeChild(veil);
                };
                if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { done(); return; }

                var fading = false;
                var fade = function () {
                    if (fading || !veil.parentNode) return;
                    fading = true;
                    veil.style.opacity = '0';
                    window.setTimeout(done, 420);
                };
                // Разметка страницы идёт следом за скриптом. Проявляем её сразу после
                // разбора документа, не дожидаясь видео: иначе заливка висит секундами.
                var reveal = function () {
                    requestAnimationFrame(function () { requestAnimationFrame(fade); });
                };
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', reveal, { once: true });
                } else {
                    reveal();
                }
                window.setTimeout(fade, 2500);
            })();
        </script>
        @endif
        {{-- То же, что @inertia, но при отказе SSR внутри #app лежит запасной текст
             (h1, текст, ссылки), а не пустой div. Vue при старте его заменяет. --}}
        @php
            if (! isset($__inertiaSsrDispatched)) {
                $__inertiaSsrDispatched = true;
                $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page);
            }
            $ssrFallback = ! $__inertiaSsrResponse && ! $isAdminArea
                && app(\App\Support\Ssr\SsrStatus::class)->failed();
        @endphp
        @if($__inertiaSsrResponse)
        {!! $__inertiaSsrResponse->body !!}
        @else
        <div id="app" data-page="{{ json_encode($page) }}">
            @if($ssrFallback)
            @include('partials.ssr-fallback')
            @endif
        </div>
        @endif
        @unless($isAdminArea)
        {{-- Аналитика по ТЗ заказчика (этап А): Метрика и GA4 грузятся только после согласия
             посетителя и не грузятся на путях из $analyticsExcluded (поиск аллергенов, личные
             разделы). На квизе (QUIET) теги есть ради целей квиза, но без просмотров и карты кликов.
             Вебвизор после согласия, поля и чувствительные блоки скрыты (resources/js/analytics/privacy.js),
             noscript-пикселя нет. Цели и просмотры страниц
             отправляет resources/js/analytics/index.js через window.alexAnalytics. --}}
        <style>
            .alex-consent { position: fixed; left: 16px; right: 16px; bottom: calc(16px + var(--mobile-dock-offset, 0px)); z-index: 2147482000; box-sizing: border-box; max-width: 440px; padding: 18px 20px; border-radius: 16px; background: #fff; color: #111; box-shadow: 0 8px 32px rgba(0, 0, 0, .18); font: 400 14px/1.45 Roboto, system-ui, -apple-system, sans-serif; }
            .alex-consent p { margin: 0 0 14px; }
            .alex-consent a { color: inherit; text-decoration: underline; }
            .alex-consent__actions { display: flex; gap: 10px; }
            .alex-consent__btn { flex: 1 1 0; min-height: 44px; padding: 10px 16px; border: 1px solid #111; border-radius: 999px; background: #fff; color: #111; font: 500 14px/1.2 Roboto, system-ui, -apple-system, sans-serif; cursor: pointer; }
            .alex-consent__btn--accept { background: #111; color: #fff; }
            @media (min-width: 1025px) { .alex-consent { left: auto; right: 24px; bottom: 24px; } }
        </style>
        <script>
            (function () {
                var YM_ID = 110363549;
                var GA_ID = 'G-YZ7B5FSFW0';
                var COOKIE = 'alex_analytics_consent';
                var VERSION = '1';
                var MAX_AGE = 60 * 60 * 24 * 180;
                // Тот же список, что EXCLUDED_PATH в resources/js/analytics/rules.js.
                var EXCLUDED = /^\/(search|cart|dashboard|profile|recover|cabinet|admin|login|forgot-password|reset-password|confirm-password|verify-email|register)(\/|$)/;
                // Тот же список, что QUIET_PATH в rules.js: цели квиза есть, просмотров и карты кликов нет.
                var QUIET = /^\/quiz(\/|$)/;
                var excludedHere = {{ $analyticsExcluded ? 'true' : 'false' }};
                var loaded = false;

                function cookieDomain() {
                    return /(^|\.)alexallergotest\.ru$/.test(location.hostname) ? '; Domain=.alexallergotest.ru' : '';
                }
                function consent() {
                    var match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=([^;]*)'));
                    if (!match) return null;
                    var parts = decodeURIComponent(match[1]).split(':');
                    return parts[0] === VERSION && (parts[1] === 'granted' || parts[1] === 'denied') ? parts[1] : null;
                }
                function save(state) {
                    document.cookie = COOKIE + '=' + VERSION + ':' + state + '; Max-Age=' + MAX_AGE + '; Path=/; SameSite=Lax'
                        + cookieDomain() + (location.protocol === 'https:' ? '; Secure' : '');
                }
                function clearAnalyticsCookies() {
                    var host = location.hostname;
                    document.cookie.split('; ').forEach(function (pair) {
                        var name = pair.split('=')[0];
                        if (!/^(_ym|_ga|_gid|_gat)/.test(name)) return;
                        ['', '; Domain=' + host, '; Domain=.' + host, '; Domain=.alexallergotest.ru'].forEach(function (domain) {
                            document.cookie = name + '=; Max-Age=0; Path=/' + domain;
                        });
                    });
                }

                function load() {
                    if (loaded || consent() !== 'granted' || EXCLUDED.test(location.pathname)) return;
                    loaded = true;

                    // Google tag пишет в свою очередь gaLayer, Метрика читает ecommerce из ymLayer (ТЗ §3.2).
                    window.gaLayer = window.gaLayer || [];
                    window.gtag = function () { window.gaLayer.push(arguments); };
                    gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
                    gtag('consent', 'update', { analytics_storage: 'granted' });
                    gtag('js', new Date());
                    gtag('config', GA_ID, { send_page_view: false });
                    var g = document.createElement('script');
                    g.async = true;
                    g.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA_ID + '&l=gaLayer';
                    document.head.appendChild(g);

                    window.ymLayer = window.ymLayer || [];
                    (function(m,e,t,r,i,k,a){
                        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
                        m[i].l=1*new Date();
                        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
                        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
                    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=' + YM_ID, 'ym');
                    // defer: просмотры отправляет только resources/js/analytics/index.js (первый и при переходах).
                    ym(YM_ID, 'init', { ssr: true, defer: true, webvisor: true, clickmap: !QUIET.test(location.pathname), trackLinks: false, accurateTrackBounce: true, ecommerce: 'ymLayer' });

                    window.dispatchEvent(new Event('alex:analytics-ready'));
                }

                // Как и раньше, не мешаем первой отрисовке: грузим после первого действия или через 5 с.
                function arm() {
                    var armed = false;
                    var events = ['scroll', 'pointerdown', 'touchstart', 'keydown'];
                    function go() {
                        if (armed) return;
                        armed = true;
                        events.forEach(function (evt) { window.removeEventListener(evt, go, { passive: true }); });
                        load();
                    }
                    events.forEach(function (evt) { window.addEventListener(evt, go, { once: true, passive: true }); });
                    setTimeout(go, 5000);
                }

                function hideBanner() {
                    var box = document.getElementById('alex-consent');
                    if (box && box.parentNode) box.parentNode.removeChild(box);
                }
                function choose(state) {
                    var previous = consent();
                    save(state);
                    hideBanner();
                    if (state === 'granted') {
                        load();
                        return;
                    }
                    clearAnalyticsCookies();
                    // Отзыв: уже загруженные теги не выгрузить, перезагружаем страницу без них.
                    if (loaded || previous === 'granted') location.reload();
                }
                function showBanner() {
                    if (document.getElementById('alex-consent')) return;
                    var box = document.createElement('div');
                    box.id = 'alex-consent';
                    box.className = 'alex-consent';
                    box.setAttribute('role', 'dialog');
                    box.setAttribute('aria-label', 'Согласие на cookie и веб-аналитику');
                    box.innerHTML = '<p>Мы используем cookie и сервисы веб-аналитики Яндекс Метрика и Google Analytics, чтобы понимать, как работает сайт. '
                        + 'Они включатся, только если вы разрешите. Подробнее — в <a href="/alex-lab/privacy">политике конфиденциальности</a>.</p>'
                        + '<div class="alex-consent__actions">'
                        + '<button type="button" class="alex-consent__btn" data-consent="denied">Отказаться</button>'
                        + '<button type="button" class="alex-consent__btn alex-consent__btn--accept" data-consent="granted">Разрешить</button>'
                        + '</div>';
                    box.addEventListener('click', function (event) {
                        var button = event.target.closest && event.target.closest('[data-consent]');
                        if (button) choose(button.getAttribute('data-consent'));
                    });
                    document.body.appendChild(box);
                }

                window.alexAnalytics = {
                    ymId: YM_ID,
                    consent: consent,
                    load: load,
                    isLoaded: function () { return loaded; },
                    openSettings: showBanner,
                };

                var state = consent();
                if (state === null) {
                    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', showBanner, { once: true });
                    else showBanner();
                } else if (state === 'granted' && !excludedHere) {
                    if (document.readyState === 'complete') arm();
                    else window.addEventListener('load', arm, { once: true });
                }
            })();
        </script>
        @endunless
    </body>
</html>
