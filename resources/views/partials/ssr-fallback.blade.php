{{--
    Запасной текст внутри #app, когда SSR не ответил (ResilientSsrGateway пометил отказ).
    Клиент монтирует Vue через createApp без гидратации, так что при загрузке JS
    этот блок просто заменяется настоящей страницей. Роботу же уходит не пустой #app,
    а h1, текст и ссылки. Адреса те же, что строят Vue-компоненты.
--}}
@php
    $fbProps = $page['props'] ?? [];
    $fbComponent = $page['component'] ?? '';
    $fbSite = (array) data_get($fbProps, 'site', []);
    $fbDoctor = (bool) ($fbSite['isDoctorsSite'] ?? false);
    $fbDoctorsUrl = function (string $path) use ($fbSite): string {
        $path = '/'.ltrim($path, '/');
        if (! empty($fbSite['isDoctorsHost'])) {
            return $path;
        }
        $origin = rtrim((string) ($fbSite['doctorsOrigin'] ?? ''), '/');
        if ($origin !== '') {
            return $origin.$path;
        }
        $prefix = rtrim((string) ($fbSite['routePrefix'] ?? '') ?: '/doctors', '/');

        return $path === '/' ? $prefix : $prefix.$path;
    };
    $fbArticleUrl = function ($slug) use ($fbDoctor, $fbDoctorsUrl): string {
        $path = '/'.ltrim((string) $slug, '/');

        return $fbDoctor ? $fbDoctorsUrl('/materials'.$path) : '/blog'.$path;
    };
    $fbItemUrl = fn ($item) => data_get($item, 'kind') === 'video'
        ? '/video/'.data_get($item, 'slug')
        : $fbArticleUrl(data_get($item, 'slug'));
    $fbAuthorUrl = function ($author): ?string {
        $key = data_get($author, 'slug') ?: data_get($author, 'id');

        return ($key === null || $key === '') ? null : '/blog/authors/'.$key;
    };
    $fbListing = $fbDoctor ? $fbDoctorsUrl('/materials') : '/blog';
    $fbNav = $fbDoctor
        ? [[$fbDoctorsUrl('/'), 'Главная'], [$fbDoctorsUrl('/materials'), 'Материалы для врачей'], ['/demo-result', 'Демо-результат'], ['/alex-lab', 'О лаборатории ALEX LAB']]
        : [['/', 'Главная'], ['/blog', 'Блог про аллергию'], ['/blog/authors', 'Авторы'], ['/quiz', 'Квиз: нужен ли вам тест на аллергены'], ['/demo-result', 'Демо-результат'], ['/alex-lab', 'О лаборатории ALEX LAB']];
@endphp
<div class="ssr-fallback" style="max-width: 860px; margin: 0 auto; padding: 24px 16px; font: 400 16px/1.5 Roboto, system-ui, -apple-system, sans-serif; color: #111;">
    <nav aria-label="Разделы сайта" style="margin-bottom: 24px;">
        @foreach($fbNav as [$fbHref, $fbLabel])
            <a href="{{ $fbHref }}" style="margin-right: 16px;">{{ $fbLabel }}</a>
        @endforeach
    </nav>

    @if($fbComponent === 'Public/Blog/Show')
        @php
            $fbBlog = (array) data_get($fbProps, 'blog', []);
            $fbRelated = data_get($fbBlog, 'related_posts') ?: data_get($fbProps, 'relatedPosts', []);
            $fbCategory = data_get($fbBlog, 'category');
            $fbAuthorHref = $fbAuthorUrl(data_get($fbBlog, 'author'));
        @endphp
        <article>
            <h1>{{ data_get($fbBlog, 'title') }}</h1>
            <p>
                <a href="{{ $fbListing }}">{{ $fbDoctor ? 'Материалы для врачей' : 'Блог' }}</a>
                @if(! $fbDoctor && data_get($fbCategory, 'slug'))
                    / <a href="/blog/{{ data_get($fbCategory, 'slug') }}">{{ data_get($fbCategory, 'name') }}</a>
                @endif
                @if($fbAuthorHref)
                    · <a href="{{ $fbAuthorHref }}">{{ data_get($fbBlog, 'author.name') }}</a>
                @endif
            </p>
            <div class="blog-content">{!! data_get($fbBlog, 'content') !!}</div>
        </article>
        @if(! empty($fbRelated))
            <section>
                <h2>Также рекомендуем</h2>
                <ul>
                    @foreach($fbRelated as $fbItem)
                        <li><a href="{{ $fbArticleUrl(data_get($fbItem, 'slug')) }}">{{ data_get($fbItem, 'title') }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif
    @elseif($fbComponent === 'Public/Blog/Index')
        @php
            $fbBlogs = (array) data_get($fbProps, 'blogs', []);
            $fbCurrentCategory = data_get($fbProps, 'currentCategory');
            $fbListPath = $fbDoctor
                ? $fbDoctorsUrl('/materials')
                : (data_get($fbCurrentCategory, 'slug') ? '/blog/'.data_get($fbCurrentCategory, 'slug') : '/blog');
            $fbType = data_get($fbProps, 'materialType') ?: data_get($fbProps, 'filters.type');
            $fbPageUrl = function (int $n) use ($fbListPath, $fbDoctor, $fbType): string {
                $q = [];
                if ($n > 1) {
                    $q['page'] = $n;
                }
                if ($fbDoctor && $fbType && $fbType !== 'all') {
                    $q['type'] = $fbType;
                }

                return $q ? $fbListPath.'?'.http_build_query($q) : $fbListPath;
            };
            $fbCurrent = (int) data_get($fbBlogs, 'current_page', 1);
            $fbLast = min((int) data_get($fbBlogs, 'last_page', 1), 50);
        @endphp
        <h1>{{ data_get($fbCurrentCategory, 'name') ?: 'Блог про аллергию' }}</h1>
        @if(data_get($fbProps, 'pageDescription'))
            <p>{{ data_get($fbProps, 'pageDescription') }}</p>
        @endif
        @if(! $fbDoctor && ! empty(data_get($fbProps, 'categories')))
            <nav aria-label="Разделы блога">
                <a href="/blog">Все</a>
                @foreach(data_get($fbProps, 'categories', []) as $fbCat)
                    · <a href="/blog/{{ data_get($fbCat, 'slug') }}">{{ data_get($fbCat, 'name') }}</a>
                @endforeach
            </nav>
        @endif
        @foreach((array) data_get($fbBlogs, 'data', []) as $fbItem)
            <article>
                <h2><a href="{{ $fbItemUrl($fbItem) }}">{{ data_get($fbItem, 'title') }}</a></h2>
                @if(data_get($fbItem, 'excerpt'))
                    <p>{{ data_get($fbItem, 'excerpt') }}</p>
                @endif
            </article>
        @endforeach
        @if($fbLast > 1)
            <nav aria-label="Страницы">
                @for($fbN = 1; $fbN <= $fbLast; $fbN++)
                    @if($fbN === $fbCurrent)
                        <span>{{ $fbN }}</span>
                    @else
                        <a href="{{ $fbPageUrl($fbN) }}">{{ $fbN }}</a>
                    @endif
                @endfor
            </nav>
        @endif
    @elseif($fbComponent === 'Public/Welcome')
        @php
            $fbHome = (array) data_get($fbProps, 'homeSettings.'.($fbDoctor ? 'doctor' : 'patient'), []);
            $fbHeroTitle = trim((string) ($fbHome['hero_title'] ?? '')) ?: ($fbDoctor
                ? 'Аллергочип ALEX² — расширенный анализ на аллергию. 300+ аллергенов'
                : 'Тест на аллергию ALEX² — один анализ, который даёт ответы');
        @endphp
        <h1>{{ $fbHeroTitle }}</h1>
        @if(trim((string) ($fbHome['hero_subtitle'] ?? '')) !== '')
            <p>{{ $fbHome['hero_subtitle'] }}</p>
        @endif
        @if(! empty(data_get($fbProps, 'meta.description')))
            <p>{{ data_get($fbProps, 'meta.description') }}</p>
        @endif
    @else
        <h1>{{ $documentTitle ?? data_get($fbProps, 'meta.title') ?? 'ALEX²' }}</h1>
        @if(! empty($documentDescription))
            <p>{{ $documentDescription }}</p>
        @endif
    @endif
</div>
