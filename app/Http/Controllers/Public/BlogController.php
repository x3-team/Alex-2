<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\DoctorVideo;
use App\Models\User;
use App\Services\DetectSite;
use App\Support\DoctorMaterialsStore;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        if ($this->isDoctorsSite() && $request->is('blog')) {
            return redirect()->to($this->doctorMaterialsUrl($request->query()), 301);
        }

        if ($redirect = $this->redirectLegacyCategoryQuery($request)) {
            return $redirect;
        }

        return $this->renderListing($request, null);
    }

    public function category(Request $request, string $categorySlug)
    {
        if ($this->publishedBlogQuery()->where('slug', $categorySlug)->exists()) {
            return $this->show($categorySlug);
        }

        $category = \App\Models\Category::where('slug', $categorySlug)->first();
        if (!$category) {
            return $this->show($categorySlug);
        }

        if ($this->isDoctorsSite()) {
            return redirect()->to($this->doctorMaterialsUrl($request->query()), 301);
        }

        return $this->renderListing($request, $category);
    }

    private function isDoctorsSite(): bool
    {
        return DetectSite::make()->isDoctorsSite();
    }

    private function doctorMaterialsUrl(array $query = []): string
    {
        unset($query['category']);

        $query = array_filter(
            $query,
            fn ($value) => $value !== null && $value !== ''
        );

        $url = '/materials';
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $url;
    }

    private function redirectLegacyCategoryQuery(Request $request)
    {
        if (!$request->filled('category')) {
            return null;
        }

        $slugs = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('category')))));
        if (count($slugs) !== 1) {
            return null;
        }

        $slug = $slugs[0];
        if (!\App\Models\Category::where('slug', $slug)->exists()) {
            return null;
        }

        if ($this->publishedBlogQuery()->where('slug', $slug)->exists()) {
            return null;
        }

        $query = $request->query();
        unset($query['category']);

        if ($this->isDoctorsSite()) {
            return redirect()->to($this->doctorMaterialsUrl($query), 301);
        }

        $target = url('/blog/'.$slug);
        if (!empty($query)) {
            $target .= '?'.http_build_query($query);
        }

        return redirect()->to($target, 301);
    }

    private function renderListing(Request $request, ?\App\Models\Category $category)
    {
        $isDoctors = $this->isDoctorsSite();
        if ($isDoctors) {
            $category = null;
        }

        $query = Blog::with(['author', 'category', 'tags'])
            ->forCurrentSite()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('is_active', true);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if ($request->filled('author')) {
            $query->where('user_id', $request->author);
        }

        if (! $isDoctors && $category) {
            $query->where('category_id', $category->id);
        } elseif (! $isDoctors && $request->filled('category')) {
            $categorySlugs = array_filter(explode(',', $request->category));

            if (count($categorySlugs) > 0) {
                $query->whereHas('category', function($q) use ($categorySlugs) {
                    $q->whereIn('slug', $categorySlugs);
                });
            }
        }

        if ($request->filled('tags')) {
            $tagSlugs = array_filter(explode(',', $request->tags));
            if (count($tagSlugs) > 0) {
                $query->whereHas('tags', function($q) use ($tagSlugs) {
                    $q->whereIn('slug', $tagSlugs);
                });
            }
        }

        $materialType = $isDoctors ? $this->materialType($request) : null;

        $blogs = $isDoctors
            ? $this->paginateDoctorFeed($request, $query, $materialType)
            : $query->orderBy('sort_order', 'asc')->latest('published_at')->paginate(3)->withQueryString();

        if (! $isDoctors) {
            $blogs->getCollection()->each->hideNonPublicAuthor();
        }

        $authors = User::where('is_admin', false)->whereHas('blogs', function($q) {
            $q->where('is_active', true)->whereNotNull('published_at')->where('published_at', '<=', now());
        })->select('id', 'name', 'avatar')->get();

        $categories = \App\Models\Category::select('id', 'name', 'slug')->orderBy('name')->get();
        $tags = \App\Models\BlogTag::select('id', 'name', 'slug')->orderBy('name')->get();
        $blogIntroDescription = \App\Models\Setting::get('blog_page_description', '');

        if ($category) {
            $pageDescription = $category->name;
            $metaTitle = $category->name.' — ALEX LAB';
            $metaDescription = $category->name;
            $metaKeywords = \App\Models\Setting::get('blog_page_meta_keywords', '');
            $filterCategory = $category->slug;
        } else {
            $pageDescription = $blogIntroDescription;
            $metaTitle = \App\Models\Setting::get('blog_page_meta_title', 'Блог — ALEX LAB');
            $metaDescription = \App\Models\Setting::get('blog_page_meta_description', '');
            $metaKeywords = \App\Models\Setting::get('blog_page_meta_keywords', '');
            $filterCategory = $request->category;
        }

        return Inertia::render('Public/Blog/Index', [
            'blogs' => $blogs,
            'authors' => $authors,
            'categories' => $categories,
            'tags' => $tags,
            'pageDescription' => $pageDescription,
            'blogIntroDescription' => $blogIntroDescription,
            'currentCategory' => $category ? [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ] : null,
            'filters' => [
                'search' => $request->search,
                'author' => $request->author,
                'category' => $filterCategory,
                'tags' => $request->tags,
                'type' => $materialType,
            ],
            'materialType' => $materialType,
            'documentCategories' => [],
            'documentFiles' => ($isDoctors && in_array($materialType, ['all', 'documents'], true))
                ? tap(new DoctorMaterialsStore(), fn ($store) => $store->ensureDefaultCategory())->files()
                : [],
            'blogMeta' => [
                'title' => $metaTitle,
                'description' => $metaDescription,
                'keywords' => $metaKeywords,
                'noindex' => $isDoctors,
            ],
        ]);
    }

    private function materialType(Request $request): string
    {
        $type = (string) $request->query('type', 'all');

        return in_array($type, ['all', 'articles', 'videos', 'documents'], true) ? $type : 'all';
    }

    private function paginateDoctorFeed(Request $request, $articleQuery, string $type): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 3;
        $options = [
            'path' => $request->root().'/materials',
            'query' => $request->query(),
        ];

        if ($type === 'documents') {
            return new LengthAwarePaginator([], 0, $perPage, 1, $options);
        }

        $items = collect();

        if ($type !== 'videos') {
            $items = $items->concat(
                $articleQuery->orderBy('sort_order')->latest('published_at')->get()
                    ->map(fn (Blog $blog) => $this->articleFeedItem($blog))
            );
        }

        if ($type !== 'articles' && ! $request->filled('tags')) {
            $items = $items->concat(
                DoctorVideo::query()
                    ->published()
                    ->with(['relatedBlog.author', 'relatedBlog.category'])
                    ->get()
                    ->map(fn (DoctorVideo $video) => $this->videoFeedItem($video))
            );
        }

        $sorted = $items->sortByDesc(fn (array $item) => $item['published_at'] ?? '')->values();

        return new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            $options
        );
    }

    private function articleFeedItem(Blog $blog): array
    {
        return [
            'id' => $blog->id,
            'kind' => 'article',
            'slug' => $blog->slug,
            'title' => $blog->title,
            'excerpt' => $blog->excerpt,
            'content' => $blog->content,
            'preview_image' => $blog->preview_image,
            'published_at' => optional($blog->published_at)->toIso8601String(),
            'duration' => $blog->duration,
            'category' => $blog->category,
            'tags' => $blog->tags,
            'author' => $blog->publicAuthor(),
        ];
    }

    private function videoFeedItem(DoctorVideo $video): array
    {
        $related = $video->relatedBlog;
        $author = $related?->publicAuthor();

        return [
            'id' => 'video-'.$video->id,
            'kind' => 'video',
            'slug' => $video->slug,
            'title' => $video->title,
            'excerpt' => $video->description,
            'content' => '',
            'preview_image' => null,
            'cover' => $video->coverUrl(),
            'published_at' => optional($video->published_at)->toIso8601String(),
            'duration' => $video->duration,
            'source_label' => $video->sourceLabel(),
            'category' => $related?->category,
            'tags' => [],
            'author' => $author,
        ];
    }

    public function authors(Request $request)
    {
        $authorCategories = \App\Models\AuthorCategory::all();

        $adminId = 1;
        $authorsQuery = \App\Models\User::where('is_admin', false);

        // 🔹 Фильтрация по SLUG категории
        if ($request->category) {
            $authorsQuery->whereHas('authorCategories', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        $authors = $authorsQuery->with(['authorCategories'])->get()->map(function ($author) {
            return [
                'id' => $author->id,
                'name' => $author->name,
                'avatar' => $author->avatar,
                'position' => $author->position ?? null,
                'credentials' => $author->credentials ?? null,
                'bio' => $author->bio ?? null,
                'is_admin' => $author->is_admin,
                'author_categories' => $author->authorCategories->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                ]),
                'articles_count' => \App\Models\Blog::where('user_id', $author->id)
                    ->where('is_active', true)
                    ->whereNotNull('published_at')
                    ->count(),
            ];
        })->values()->toArray();

        $pageDescription = \App\Models\Setting::firstWhere('key', 'authors_page_description')?->value ?? '';

        // 🔹 🔥 Добавляем получение Meta-тегов
        $metaTitle = \App\Models\Setting::get('authors_page_meta_title', 'Авторы — ALEX LAB');
        $metaDescription = \App\Models\Setting::get('authors_page_meta_description', '');
        $metaKeywords = \App\Models\Setting::get('authors_page_meta_keywords', '');

        return Inertia::render('Public/Blog/Authors', [
            'authors' => $authors,
            'categories' => $authorCategories->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
            ])->values()->toArray(),
            'pageDescription' => $pageDescription,
            'filters' => ['category' => $request->category],
            // 🔹 🔥 Передаем meta-теги
            'authorsMeta' => [
                'title' => $metaTitle,
                'description' => $metaDescription,
                'keywords' => $metaKeywords,
            ],
        ]);
    }



    public function author($id, Request $request)
    {
                $author = \App\Models\User::with(['authorCategories'])
            ->where(function ($query) {
                $query->where('is_admin', true)
                    ->orWhereHas('blogs', function ($blogs) {
                        $blogs->where('is_active', true)
                            ->whereNotNull('published_at')
                            ->where('published_at', '<=', now());
                    });
            })
            ->findOrFail($id);

        // 🔹 Запрос постов автора
        $blogsQuery = \App\Models\Blog::with(['author', 'category', 'tags'])
            ->forCurrentSite()
            ->where('user_id', $author->id)
            ->where('is_active', true)
            ->whereNotNull('published_at');

        // 🔹 🔥 ИЗМЕНЕНО: Фильтрация по нескольким категориям (через запятую)
        if ($request->filled('category')) {
            $categorySlugs = array_filter(explode(',', $request->category));
            if (count($categorySlugs) > 0) {
                $blogsQuery->whereHas('category', function($q) use ($categorySlugs) {
                    $q->whereIn('slug', $categorySlugs);
                });
            }
        }

        // 🔹 🔥 Фильтрация по тегам
        if ($request->filled('tags')) {
            $tagSlugs = array_filter(explode(',', $request->tags));
            if (count($tagSlugs) > 0) {
                $blogsQuery->whereHas('tags', function($q) use ($tagSlugs) {
                    $q->whereIn('slug', $tagSlugs);
                });
            }
        }

        // 🔹 🔥 Пагинация
        // 🔹 Пагинация (Сортируем по дате публикации)
        $blogs = $blogsQuery->latest('published_at')->paginate(3)->withQueryString(); // 👈 Было: latest()

        // 🔹 🔥 Получаем ВСЕ теги, которые есть у публикаций этого автора (БЕЗ фильтра)
        $allAuthorBlogs = \App\Models\Blog::where('user_id', $author->id)
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->with('tags')
            ->get();

        $tagIds = $allAuthorBlogs->pluck('tags')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->filter();

        $tags = \App\Models\BlogTag::whereIn('id', $tagIds)
            ->select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();

        // 🔹 Категории с slug
        $authorCategories = \App\Models\Category::whereIn('id',
            \App\Models\Blog::where('user_id', $author->id)
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->pluck('category_id')
        )->select('id', 'name', 'slug')->get();

        $authorTitle = trim((string) ($author->name ?? '')) !== ''
            ? ($author->name . ' — эксперт ALEX LAB')
            : 'Автор — ALEX LAB';
        $authorDescription = trim(strip_tags((string) ($author->bio ?? '')));
        if ($authorDescription === '') {
            $authorDescription = 'Публикации и материалы эксперта лаборатории ALEX LAB.';
        } elseif (mb_strlen($authorDescription) > 180) {
            $authorDescription = mb_substr($authorDescription, 0, 177) . '...';
        }

        return Inertia::render('Public/Blog/Author', [
            'author' => [
                'id' => $author->id,
                'name' => $author->name,
                'avatar' => $author->avatar,
                'position' => $author->position,
                'credentials' => $author->credentials,
                'bio' => $author->bio,
                'career_history' => $author->career_history,
                'education' => $author->education,
                'author_categories' => $author->authorCategories->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                ]),
            ],
            'blogs' => $blogs,
            'categories' => $authorCategories,
            'tags' => $tags,
            'filters' => [
                'category' => $request->category,
                'tags' => $request->tags,
            ],
            'authorMeta' => [
                'title' => $authorTitle,
                'description' => $authorDescription,
                'keywords' => '',
            ],
        ]);
    }

    private function publishedBlogQuery()
    {
        return Blog::query()
            ->forCurrentSite()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function show($slug)
    {
        $blog = Blog::with(['author', 'category', 'tags', 'relatedPosts.author', 'relatedPosts.category'])
            ->forCurrentSite()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();

        $isPublicRelated = function ($post) {
            if (!$post) return false;
            if (!$post->is_active) return false;
            if (!$post->published_at) return false;
            return $post->published_at->lte(now());
        };

        $curated = $blog->relatedPosts->filter($isPublicRelated)->values();

        $relatedPosts = $curated->isNotEmpty()
            ? $curated
            : $this->publishedBlogQuery()
                ->with(['author', 'category'])
                ->where('id', '!=', $blog->id)
                ->when($blog->category_id, fn($q) => $q->where('category_id', $blog->category_id))
                ->latest('published_at')
                ->limit(3)
                ->get();

        $blog->hideNonPublicAuthor();
        $relatedPosts->each->hideNonPublicAuthor();

        // 🔹 ГЕНЕРАЦИЯ JSON-LD
        $currentUrl = url()->current();
        $publicAuthor = $blog->publicAuthor();

        $articleSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $blog->seo_title ?: $blog->title,
            'description' => $blog->seo_description ?: $blog->excerpt,
            'datePublished' => $blog->published_at ? $blog->published_at->toIso8601String() : $blog->created_at->toIso8601String(),
            'dateModified' => $blog->updated_at->toIso8601String(),
            'author' => $publicAuthor
                ? [
                    '@type' => 'Person',
                    'name' => $publicAuthor->name,
                    'url' => url('/blog/author/' . $publicAuthor->id),
                ]
                : [
                    '@type' => 'Organization',
                    'name' => 'ALEX LAB',
                ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'ALEX LAB',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => rtrim((string) config('app.url'), '/') . '/og-favicon.png',
                    'width' => 512,
                    'height' => 512,
                ]
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $currentUrl,
            ]
        ];

        if (!empty($blog->preview_image)) {
            $articleSchema['image'] = asset('storage/' . $blog->preview_image);
        }

        if ($publicAuthor && $publicAuthor->name) {
            $articleSchema['reviewedBy'] = [
                '@type' => 'Person',
                'name' => $publicAuthor->name,
                'url' => url('/blog/author/' . $publicAuthor->id),
            ];
        }

        $faqs = is_array($blog->faqs) ? $blog->faqs : [];
        $faqEntities = [];
        foreach ($faqs as $faq) {
            $q = trim((string) ($faq['question'] ?? ''));
            $a = trim(strip_tags((string) ($faq['answer'] ?? '')));
            if ($q === '' || $a === '') {
                continue;
            }
            $faqEntities[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $a,
                ],
            ];
            if (preg_match('/сколько\s+стоит/iu', $q) && preg_match('/(\d[\d\s]{1,8})\s*(₽|руб(?:лей|ля)?)/iu', $a, $pm)) {
                $price = preg_replace('/\s+/', '', $pm[1]);
                if ($price !== '' && ctype_digit($price)) {
                    $articleSchema['offers'] = [
                        '@type' => 'Offer',
                        'price' => $price,
                        'priceCurrency' => 'RUB',
                    ];
                }
            }
        }
        $faqPageSchema = null;
        if ($faqEntities) {
            $faqPageSchema = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faqEntities,
            ];
        }

        $breadcrumbSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Главная',
                    'item' => url('/'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Блог',
                    'item' => url('/blog'),
                ],
            ]
        ];

        $crumbPosition = 3;
        if (!empty($blog->category?->slug) && !empty($blog->category?->name)) {
            $breadcrumbSchema['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $crumbPosition,
                'name' => $blog->category->name,
                'item' => url('/blog/'.$blog->category->slug),
            ];
            $crumbPosition++;
        }

        $breadcrumbSchema['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $crumbPosition,
            'name' => $blog->title,
            'item' => $currentUrl,
        ];

        $relatedArray = $relatedPosts->values()->all();
        $blogPayload = $blog->toArray();
        $blogPayload['related_posts'] = json_decode(json_encode($relatedArray), true);
        $blogPayload['average_rating'] = $blog->averageRating();
        $blogPayload['ratings_count'] = $blog->ratingsCount();

        $seoJsonLd = [$articleSchema, $breadcrumbSchema];
        if ($faqPageSchema) {
            $seoJsonLd[] = $faqPageSchema;
        }

        $inertia = Inertia::render('Public/Blog/Show', [
            'blog' => $blogPayload,
            'relatedPosts' => $relatedArray,
            'articleJsonLd' => json_encode($articleSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'breadcrumbJsonLd' => json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'seoJsonLd' => $seoJsonLd,
        ]);

        if ($blog->noindex) {
            return $inertia->toResponse(request())->withHeaders([
                'X-Robots-Tag' => 'noindex',
            ]);
        }

        return $inertia;
    }
    public function rate(Request $request, Blog $blog)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'visitor_id' => 'required|string',
        ]);

        // Проверяем, не оценивал ли уже этот пользователь
        $existingRating = \App\Models\BlogRating::where('blog_id', $blog->id)
            ->where('visitor_id', $request->visitor_id)
            ->first();

        if ($existingRating) {
            // Обновляем оценку
            $existingRating->update(['rating' => $request->rating]);
        } else {
            // Создаём новую оценку
            \App\Models\BlogRating::create([
                'blog_id' => $blog->id,
                'visitor_id' => $request->visitor_id,
                'rating' => $request->rating,
            ]);
        }

        // Возвращаем средний рейтинг
        $averageRating = round($blog->ratings()->avg('rating'), 1);
        $ratingsCount = $blog->ratings()->count();

        return response()->json([
            'success' => true,
            'average_rating' => $averageRating,
            'ratings_count' => $ratingsCount,
        ]);
    }
}