<?php

namespace App\Http\Controllers\Doctors;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesDoctorsRoutes;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\BlogTag;
use App\Models\DoctorMaterial;
use App\Services\DetectSite;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DoctorMaterialsController extends Controller
{
    use ResolvesDoctorsRoutes;

    public function index(Request $request, DetectSite $detectSite): Response
    {
        $tab = $request->string('tab', 'articles')->toString();
        if (! in_array($tab, ['articles', 'video'], true)) {
            $tab = 'articles';
        }

        $sort = $request->string('sort', 'newest')->toString();
        $category = $request->string('category')->toString() ?: null;
        $tag = $request->string('tag')->toString() ?: null;

        $query = Blog::query()
            ->with(['author.authorCategories', 'category', 'tags'])
            ->forAudience(DetectSite::MODE_DOCTORS)
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        if ($tab === 'video') {
            $query->whereNotNull('video_url');
        } else {
            $query->where(function ($builder) {
                $builder->whereNull('video_url')->orWhere('video_url', '');
            });
        }

        if ($category) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        if ($tag) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $tag));
        }

        match ($sort) {
            'oldest' => $query->orderBy('published_at'),
            'title' => $query->orderBy('title'),
            default => $query->orderByDesc('published_at'),
        };

        $posts = $query
            ->paginate(5)
            ->withQueryString()
            ->through(fn (Blog $blog) => $this->transformBlog($blog));

        return Inertia::render('Doctors/Materials/Index', [
            'posts' => $posts,
            'categories' => BlogCategory::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'tags' => BlogTag::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'filters' => [
                'tab' => $tab,
                'sort' => $sort,
                'category' => $category,
                'tag' => $tag,
            ],
            'documentPlaques' => $this->documentPlaques(),
            'seoMeta' => [
                'title' => 'Материалы для врачей — ALEX LAB',
                'description' => 'Статьи, видео и документы лаборатории для специалистов и пациентов.',
                'keywords' => 'материалы для врачей, аллергология, ALEX2, документы лаборатории',
            ],
            'site' => $this->sitePayload($detectSite),
        ]);
    }

    /**
     * Legacy path used on production before /doctors/materials.
     */
    public function legacy(Request $request, DetectSite $detectSite): Response
    {
        return $this->index($request, $detectSite);
    }

    protected function transformBlog(Blog $blog): array
    {
        return [
            'id' => $blog->id,
            'title' => $blog->title,
            'slug' => $blog->slug,
            'excerpt' => $blog->excerpt,
            'preview_image' => $blog->preview_image,
            'published_at' => optional($blog->published_at)?->toIso8601String(),
            'duration' => $blog->duration,
            'video_url' => $blog->video_url ?? null,
            'video_platform' => $blog->video_platform ?? null,
            'category' => $blog->category ? [
                'id' => $blog->category->id,
                'name' => $blog->category->name,
                'slug' => $blog->category->slug,
            ] : null,
            'tags' => $blog->tags?->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->values()->all() ?? [],
            'author' => $blog->author ? [
                'id' => $blog->author->id,
                'name' => $blog->author->name,
                'avatar' => $blog->author->avatar,
                'author_categories' => $blog->author->authorCategories?->map(fn ($cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                ])->values()->all() ?? [],
            ] : null,
            'url' => '/blog/'.$blog->slug,
        ];
    }

    protected function documentPlaques(): array
    {
        $defaults = [
            [
                'key' => 'licenses',
                'title' => 'Лицензии и аккредитации',
                'description' => 'Разрешительная документация лаборатории',
                'count' => null,
            ],
            [
                'key' => 'instructions',
                'title' => 'Инструкции и методики',
                'description' => 'Методические материалы для специалистов',
                'count' => null,
            ],
            [
                'key' => 'forms',
                'title' => 'Бланки и бланки',
                'description' => 'Формы и шаблоны для работы с пациентами',
                'count' => null,
            ],
            [
                'key' => 'quality',
                'title' => 'Контроль качества',
                'description' => 'Документы системы менеджмента качества',
                'count' => null,
            ],
        ];

        if (! class_exists(DoctorMaterial::class)) {
            return $defaults;
        }

        $materials = DoctorMaterial::query()->get(['id', 'title', 'category']);

        if ($materials->isEmpty()) {
            return $defaults;
        }

        $grouped = $materials->groupBy(fn ($item) => $item->category ?: 'other');

        return collect($defaults)->map(function (array $plaque) use ($grouped) {
            $plaque['count'] = $grouped->get($plaque['key'], collect())->count() ?: null;

            return $plaque;
        })->all();
    }

    protected function sitePayload(DetectSite $detectSite): array
    {
        return [
            'mode' => $detectSite->mode(),
            'isDoctorsSite' => $detectSite->isDoctorsSite(),
            'themeColor' => $detectSite->themeColor(),
            'routePrefix' => $detectSite->routePrefix(),
        ];
    }
}
