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
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"@if($paintAudience) style="background-color: {{ $audienceColor }}"@endif>
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
        @if(request()->is('admin', 'admin/*', 'login', 'forgot-password', 'reset-password', 'reset-password/*', 'confirm-password', 'verify-email', 'verify-email/*', 'register'))
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
                || (bool) data_get($seoProps, 'seoMeta.noindex')
                || ($isDoctorsSite && $seoPath !== '/' && $seoPath !== '');
            $robotsMeta = ($blogNoindex || in_array($seoPath, $noindexPaths, true))
                ? ($blogNoindex ? 'noindex' : 'noindex, nofollow')
                : null;

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
                $documentDescription = $descriptionFallbacks[$seoPath]
                    ?? (str_starts_with((string) $seoPath, 'blog')
                        ? $descriptionFallbacks['blog']
                        : 'Лаборатория ALEX LAB — тест на аллергию ALEX².');
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
                'consent' => [
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

            $appUrl = rtrim((string) config('app.url'), '/');
            if ($isArticle) {
                $articleCanonical = trim((string) ($blog['canonical_url'] ?? ''));
                $canonicalUrl = $articleCanonical !== ''
                    ? $articleCanonical
                    : $appUrl.'/blog/'.ltrim((string) $blog['slug'], '/');
            } elseif ($seoPath === '/' || $seoPath === '') {
                $canonicalUrl = $appUrl;
            } else {
                $canonicalUrl = $appUrl.'/'.ltrim((string) $seoPath, '/');
            }

            // Пагинация блога/автора: canonical на саму страницу (?page=N при N>1)
            $pageNum = (int) request()->query('page', 1);
            if (
                $pageNum > 1
                && !$isArticle
                && (
                    $seoPath === 'blog'
                    || preg_match('#^blog/author/\d+$#', (string) $seoPath)
                    || (
                        preg_match('#^blog/[A-Za-z0-9_-]+$#', (string) $seoPath)
                        && $seoPath !== 'blog/authors'
                    )
                )
            ) {
                $canonicalUrl .= '?page='.$pageNum;
            }

            // Doctor-only URLs must not canonicalize to apex 404s (/video, /materials).
            if ($isDoctorsSite && (str_starts_with((string) $seoPath, 'video') || str_starts_with((string) $seoPath, 'materials'))) {
                $doctorOrigin = rtrim((string) data_get($seoProps, 'site.doctorsOrigin'), '/');
                if ($doctorOrigin === '') {
                    $doctorOrigin = rtrim((string) request()->getSchemeAndHttpHost(), '/');
                }
                $canonicalUrl = $doctorOrigin.'/'.ltrim((string) $seoPath, '/');
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
                $ogImage = $appUrl.'/storage/'.ltrim((string) $blog['preview_image'], '/');
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
                $ogImage = $appUrl.'/og-image.png';
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

                var done = function () { if (veil && veil.parentNode) veil.parentNode.removeChild(veil); };
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
        @inertia
        <script>
            (function () {
                var loaded = false;
                function loadAnalytics() {
                    if (loaded) return;
                    loaded = true;
                    window.dataLayer = window.dataLayer || [];
                    function gtag(){dataLayer.push(arguments);}
                    window.gtag = window.gtag || gtag;
                    var g = document.createElement('script');
                    g.async = true;
                    g.src = 'https://www.googletagmanager.com/gtag/js?id=G-YZ7B5FSFW0';
                    g.onload = function () {
                        gtag('js', new Date());
                        gtag('config', 'G-YZ7B5FSFW0');
                    };
                    document.head.appendChild(g);

                    (function(m,e,t,r,i,k,a){
                        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
                        m[i].l=1*new Date();
                        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
                        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
                    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=110363549', 'ym');
                    ym(110363549, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
                }
                function arm() {
                    var armed = false;
                    function go() {
                        if (armed) return;
                        armed = true;
                        loadAnalytics();
                        ['scroll', 'pointerdown', 'touchstart', 'keydown'].forEach(function (evt) {
                            window.removeEventListener(evt, go, { passive: true });
                        });
                    }
                    ['scroll', 'pointerdown', 'touchstart', 'keydown'].forEach(function (evt) {
                        window.addEventListener(evt, go, { once: true, passive: true });
                    });
                    setTimeout(go, 5000);
                }
                if (document.readyState === 'complete') arm();
                else window.addEventListener('load', arm, { once: true });
            })();
        </script>
        <noscript><div><img src="https://mc.yandex.ru/watch/110363549" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    </body>
</html>
