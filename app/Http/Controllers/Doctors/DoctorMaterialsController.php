<?php

namespace App\Http\Controllers\Doctors;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesDoctorsRoutes;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\BlogTag;
use App\Models\DoctorMaterial;
use App\Services\DetectSite;
use App\Support\DoctorsCopy;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DoctorMaterialsController extends Controller
{
    use ResolvesDoctorsRoutes;

    public const FALLBACK_FILTERS = [
        ['id' => 'algorithms', 'name' => 'Алгоритмы', 'slug' => 'algorithms'],
        ['id' => 'myths', 'name' => 'Мифы и факты', 'slug' => 'myths'],
        ['id' => 'diagnostics', 'name' => 'Диагностика', 'slug' => 'diagnostics'],
        ['id' => 'news', 'name' => 'Новости и события', 'slug' => 'news'],
        ['id' => 'announcements', 'name' => 'Анонсы', 'slug' => 'announcements'],
        ['id' => 'errors', 'name' => 'Ошибки врача', 'slug' => 'errors'],
        ['id' => 'seminars', 'name' => 'Семинары', 'slug' => 'seminars'],
    ];

    public function index(Request $request, DetectSite $detectSite): Response
    {
        $tab = $request->string('tab', 'all')->toString();
        if (! in_array($tab, ['all', 'articles', 'video'], true)) {
            $tab = 'all';
        }

        $sort = $request->string('sort', 'newest')->toString();
        $category = $request->string('category')->toString() ?: null;
        $tag = $request->string('tag')->toString() ?: null;
        $q = trim($request->string('q')->toString());

        $blogTable = (new Blog)->getTable();
        $hasBlog = Schema::hasTable($blogTable);
        $hasVideo = $hasBlog && Schema::hasColumn($blogTable, 'video_url');

        if ($hasBlog) {
            $query = Blog::query()
                ->with(['author.authorCategories', 'category', 'tags']);

            if (Schema::hasColumn($blogTable, 'audience')) {
                $query->forAudience(DetectSite::MODE_DOCTORS);
            }

            $query
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now());

            if ($q !== '') {
                $query->where(function ($builder) use ($q) {
                    $builder->where('title', 'like', '%'.$q.'%')
                        ->orWhere('excerpt', 'like', '%'.$q.'%');
                });
            }

            if ($hasVideo) {
                if ($tab === 'video') {
                    $query->whereNotNull('video_url')->where('video_url', '!=', '');
                } elseif ($tab === 'articles') {
                    $query->where(function ($builder) {
                        $builder->whereNull('video_url')->orWhere('video_url', '');
                    });
                }
            } elseif ($tab === 'video') {
                $query->whereRaw('0 = 1');
            }

            if ($category) {
                $query->whereHas('category', fn ($builder) => $builder->where('slug', $category));
            }

            if ($tag) {
                $query->whereHas('tags', fn ($builder) => $builder->where('slug', $tag));
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
        } else {
            $posts = new LengthAwarePaginator([], 0, 5, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }

        $categoryTable = (new BlogCategory)->getTable();
        $categories = Schema::hasTable($categoryTable)
            ? BlogCategory::query()->orderBy('name')->get(['id', 'name', 'slug'])
            : collect();

        return Inertia::render('Doctors/Materials/Index', [
            'posts' => $posts,
            'categories' => $categories->isNotEmpty() ? $categories : self::FALLBACK_FILTERS,
            'tags' => Schema::hasTable((new BlogTag)->getTable())
                ? BlogTag::query()->orderBy('name')->get(['id', 'name', 'slug'])
                : collect(),
            'filters' => [
                'tab' => $tab,
                'sort' => $sort,
                'category' => $category,
                'tag' => $tag,
                'q' => $q !== '' ? $q : null,
            ],
            'documentPlaques' => $this->documentPlaques(),
            'seoMeta' => [
                'title' => 'Материалы для врачей — ALEX LAB',
                'description' => 'Статьи, видеолекции и документы лаборатории о молекулярной диагностике ALEX2 — для специалистов и пациентов.',
                'keywords' => 'материалы для врачей, аллергология, ALEX2, документы лаборатории',
            ],
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

    public static function documentLabel(int $count): string
    {
        return DoctorsCopy::documentLabel($count);
    }

    protected function documentPlaques(): array
    {
        $defaults = [
            [
                'key' => 'licenses',
                'title' => 'Лицензии и аккредитации',
                'description' => 'Разрешительная документация лаборатории',
                'count' => null,
                'count_label' => null,
            ],
            [
                'key' => 'instructions',
                'title' => 'Инструкции и методики',
                'description' => 'Методические материалы для специалистов',
                'count' => null,
                'count_label' => null,
            ],
            [
                'key' => 'forms',
                'title' => 'Бланки и формы',
                'description' => 'Формы и шаблоны для работы с пациентами',
                'count' => null,
                'count_label' => null,
            ],
            [
                'key' => 'quality',
                'title' => 'Контроль качества',
                'description' => 'Документы системы менеджмента качества',
                'count' => null,
                'count_label' => null,
            ],
        ];

        $table = (new DoctorMaterial)->getTable();

        if (! Schema::hasTable($table)) {
            return $defaults;
        }

        $columns = ['id', 'title'];
        if (Schema::hasColumn($table, 'category')) {
            $columns[] = 'category';
        }

        $materials = DoctorMaterial::query()->get($columns);

        if ($materials->isEmpty()) {
            return $defaults;
        }

        $grouped = $materials->groupBy(fn ($item) => $item->category ?: 'other');

        return collect($defaults)->map(function (array $plaque) use ($grouped) {
            $count = $grouped->get($plaque['key'], collect())->count();
            $plaque['count'] = $count ?: null;
            $plaque['count_label'] = $count ? self::documentLabel($count) : null;

            return $plaque;
        })->all();
    }
}
