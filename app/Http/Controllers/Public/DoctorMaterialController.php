<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\DoctorVideo;
use App\Models\Setting;
use App\Support\DoctorMaterialsStore;
use Inertia\Inertia;
use Inertia\Response;

class DoctorMaterialController extends Controller
{
    public function index(): Response
    {
        $store = new DoctorMaterialsStore();
        $store->ensureDefaultCategory();

        $articles = Blog::query()
            ->with(['author', 'category', 'tags'])
            ->forCurrentSite()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('is_active', true)
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (Blog $blog) => $this->articleCard($blog));

        $videos = DoctorVideo::query()
            ->published()
            ->with(['relatedBlog.author', 'relatedBlog.category'])
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (DoctorVideo $video) => $this->videoCard($video));

        $feed = $articles
            ->concat($videos)
            ->sortByDesc(fn (array $item) => $item['published_at'] ?? '')
            ->values();

        return Inertia::render('Public/DoctorMaterials', [
            'view' => 'all',
            'feed' => $feed,
            'categories' => $store->categoriesWithCounts(),
            'materials' => $store->files(),
            'seoMeta' => $this->seoMeta('Материалы для врачей — ALEX LAB', 'Статьи, видеолекции и документы лаборатории.'),
        ]);
    }

    public function documents(): Response
    {
        $store = new DoctorMaterialsStore();
        $store->ensureDefaultCategory();

        return Inertia::render('Public/DoctorMaterials', [
            'view' => 'documents',
            'feed' => [],
            'categories' => $store->categoriesWithCounts(),
            'materials' => $store->files(),
            'seoMeta' => $this->seoMeta(),
        ]);
    }

    public function category(string $categorySlug): Response
    {
        if ($categorySlug === 'documents') {
            return $this->documents();
        }

        $store = new DoctorMaterialsStore();
        $store->ensureDefaultCategory();

        $category = $store->categoryBySlug($categorySlug);
        if (! $category) {
            abort(404);
        }

        $files = $store->filesForCategory($category['id']);
        $others = array_values(array_filter(
            $store->categoriesWithCounts(),
            fn ($item) => $item['id'] !== $category['id']
        ));

        return Inertia::render('Public/DoctorMaterialCategory', [
            'category' => [
                ...$category,
                'count' => count($files),
                'count_label' => DoctorMaterialsStore::ruDocuments(count($files)),
            ],
            'materials' => $files,
            'otherCategories' => $others,
            'seoMeta' => $this->seoMeta(),
        ]);
    }

    private function articleCard(Blog $blog): array
    {
        $author = $blog->author;

        return [
            'type' => 'article',
            'id' => $blog->id,
            'title' => $blog->title,
            'slug' => $blog->slug,
            'description' => $blog->excerpt,
            'cover' => $blog->preview_image ? '/storage/'.$blog->preview_image : null,
            'duration' => $blog->duration,
            'published_at' => optional($blog->published_at)->toDateString(),
            'category' => $blog->category?->name,
            'tag' => $blog->tags?->first()?->name,
            'author' => $author ? [
                'id' => $author->id,
                'name' => $author->name,
                'avatar' => $author->avatar ? '/storage/'.$author->avatar : null,
                'role' => data_get($author, 'author_categories.0.name')
                    ?? data_get($author, 'authorCategories.0.name'),
            ] : null,
        ];
    }

    private function videoCard(DoctorVideo $video): array
    {
        $author = $video->relatedBlog?->author;

        return [
            'type' => 'video',
            ...$video->toCardArray(),
            'category' => $video->relatedBlog?->category?->name,
            'author' => $author ? [
                'id' => $author->id,
                'name' => $author->name,
                'avatar' => $author->avatar ? '/storage/'.$author->avatar : null,
                'role' => data_get($author, 'author_categories.0.name'),
            ] : null,
        ];
    }

    private function seoMeta(?string $title = null, ?string $description = null): array
    {
        return [
            'title' => $title ?: Setting::get('doctor_materials_meta_title', 'Документы для врачей — ALEX LAB'),
            'description' => $description ?: Setting::get('doctor_materials_meta_description', 'Регистрационные документы, инструкции и бланки лаборатории.'),
            'keywords' => Setting::get('doctor_materials_meta_keywords', ''),
            'noindex' => true,
        ];
    }
}
