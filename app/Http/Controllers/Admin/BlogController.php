<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\ImageService;
use App\Models\Redirect;
use App\Support\AdminPublishedAt;

class BlogController extends Controller
{
    protected ImageService $imageService;
    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }
    public function index(Request $request)
    {
        $query = Blog::with(['author.authorCategories', 'category', 'tags'])
            ->whereNull('deleted_at'); // 🔹 Только неудалённые

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        if ($request->filled('tags')) {
            $tagSlugs = array_filter(explode(',', $request->tags));
            if (count($tagSlugs) > 0) {
                $query->whereHas('tags', function($q) use ($tagSlugs) {
                    $q->whereIn('slug', $tagSlugs);
                });
            }
        }

        $blogs = $query->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $authors = User::select('id', 'name', 'avatar')->orderBy('name')->get();
        $categories = \App\Models\Category::select('id', 'name', 'slug')->orderBy('name')->get();
        $tags = \App\Models\BlogTag::select('id', 'name', 'slug')->orderBy('name')->get();

        return Inertia::render('Admin/Blog/Index', [
            'blogs' => $blogs,
            'authors' => $authors,
            'categories' => $categories,
            'tags' => $tags,
            'pageDescription' => \App\Models\Setting::get('blog_page_description', ''),
            'filters' => [
                'search' => $request->search,
                'category' => $request->category,
                'tags' => $request->tags,
            ],
            'blogMeta' => [
                'title' => \App\Models\Setting::get('blog_page_meta_title', 'Блог — АLEX LAB'),
                'description' => \App\Models\Setting::get('blog_page_meta_description', ''),
                'keywords' => \App\Models\Setting::get('blog_page_meta_keywords', ''),
            ],
        ]);
    }
    public function trashed(Request $request)
    {
        $query = Blog::with(['author', 'category'])
            ->onlyTrashed(); // 🔹 Только удалённые

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $blogs = $query->latest('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Blog/Trashed', [
            'blogs' => $blogs,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }


    public function create()
    {
        $authors = \App\Models\User::select('id', 'name')->get();
        $categories = \App\Models\Category::select('id', 'name')->orderBy('name')->get();
        // 🔹 Загружаем все статьи для селектора
        $allBlogs = Blog::select('id', 'title', 'slug', 'category_id')
            ->orderBy('title')
            ->get();
        return Inertia::render('Admin/Blog/Create', [
            'authors' => $authors,
            'categories' => $categories,
            'tags' => \App\Models\BlogTag::all(),
            'selectedTags' => [], // ✅ При создании нового поста выбранных тегов нет
            'allBlogs' => $allBlogs,
            'audienceOptions' => [
                ['value' => 'patients', 'label' => 'Пациенты'],
                ['value' => 'doctors', 'label' => 'Врачи'],
            ],
        ]);
    }


    private function generateTableOfContents(string $content): array
    {
        $toc = [];

        // 🔹 Ищем все заголовки h2-h5 с помощью regex
        $pattern = '/<h([2-5])([^>]*)>(.*?)<\/h\1>/is';

        $content = preg_replace_callback($pattern, function ($matches) use (&$toc) {
            $level = (int)$matches[1];
            $attributes = $matches[2];
            $innerHTML = $matches[3];

            // 🔹 Извлекаем только текст (без HTML-тегов)
            $text = trim(strip_tags($innerHTML));

            if (!$text) return $matches[0];

            // 🔹 Генерируем якорь из текста
            $anchor = mb_strtolower(trim(
                preg_replace('/[^а-яА-ЯёЁ\w\s-]/u', '', $text)
            ));
            $anchor = preg_replace('/[\s_]+/u', '-', $anchor);
            $anchor = trim($anchor, '-');

            if (!$anchor) {
                $anchor = 'heading-' . uniqid();
            }

            // 🔹 🔥 В содержание добавляем ТОЛЬКО H2
            if ($level === 2) {
                $toc[] = [
                    'text' => $text,
                    'anchor' => $anchor,
                    'level' => $level
                ];
            }

            // 🔹 Добавляем id к заголовку (не ломая структуру!)
            // id добавляем ко ВСЕМ заголовкам (h2-h5), чтобы якоря работали
            if (preg_match('/\bid\s*=\s*["\'][^"\']+["\']/', $attributes)) {
                // Уже есть id — заменяем
                $newAttributes = preg_replace(
                    '/\bid\s*=\s*["\'][^"\']+["\']/',
                    'id="' . $anchor . '"',
                    $attributes
                );
                return '<h' . $level . $newAttributes . '>' . $innerHTML . '</h' . $level . '>';
            } else {
                // Добавляем id
                return '<h' . $level . ' id="' . $anchor . '"' . $attributes . '>' . $innerHTML . '</h' . $level . '>';
            }
        }, $content);

        return [
            'content' => $content,
            'toc' => $toc
        ];
    }

    private function calculateReadingTime(string $content): string
    {
        // 1. Удаляем все HTML-теги
        $text = strip_tags($content);

        // 2. Считаем слова (\p{L} означает "любая буква любого языка", включая русскую)
        preg_match_all('/\p{L}+/u', $text, $matches);
        $wordCount = count($matches[0]);

        // 🔹 🔹 🔹 ВАЖНО: Записываем реальные данные в лог Laravel
        \Illuminate\Support\Facades\Log::info('⏱️ Расчет времени чтения', [
            'длина HTML (символов)' => strlen($content),
            'длина текста (символов)' => strlen($text),
            'найденное количество слов' => $wordCount,
        ]);

        // 3. Считаем минуты (180 слов в минуту — средняя скорость чтения)
        $minutes = ceil($wordCount / 180);

        if ($minutes <= 1) return '1 мин';
        if ($minutes < 60) return "{$minutes} мин";

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return $mins > 0 ? "{$hours} ч {$mins} мин" : "{$hours} ч";
    }
    private function slugifyTitle(string $title): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'z','з'=>'z',
            'и'=>'i','й'=>'i','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
            'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'x','ц'=>'c','ч'=>'c','ш'=>'s','щ'=>'shh',
            'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'iu','я'=>'ia',
        ];
        $title = mb_strtolower($title, 'UTF-8');
        $out = '';
        $len = mb_strlen($title, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($title, $i, 1, 'UTF-8');
            $out .= array_key_exists($ch, $map) ? $map[$ch] : $ch;
        }
        $out = preg_replace('/[^a-z0-9\s-]/', '', $out);
        $out = preg_replace('/[\s_-]+/', '-', $out);
        return trim((string) $out, '-');
    }

    private function generateSlugWithCategory(string $title, ?int $categoryId = null): string
    {
        $categorySlug = '';
        if ($categoryId) {
            $category = \App\Models\Category::find($categoryId);
            $categorySlug = $category?->slug ?? '';
        }
        $titleSlug = \App\Models\Blog::slugifyTitle($title);

        return $categorySlug ? "{$categorySlug}/{$titleSlug}" : $titleSlug;
    }

    private function lastSlugSegment(string $slug): string
    {
        $parts = explode('/', trim($slug, '/'));
        return (string) end($parts);
    }

    private function postedSlugIsWeak(?string $slug): bool
    {
        $slug = trim((string) $slug);
        if ($slug === '') {
            return true;
        }
        if (preg_match('/[А-Яа-яЁё]/u', $slug)) {
            return true;
        }
        $last = $this->lastSlugSegment($slug);
        return $last === '' || preg_match('/^[\d-]+$/', $last);
    }

    private function resolveBlogSlug(array $validated, $request, ?int $excludeId = null): string
    {
        $posted = trim((string) ($validated['slug'] ?? ''));
        if ($this->postedSlugIsWeak($posted)) {
            $slug = $this->generateSlugWithCategory(
                $validated['title'],
                $request->category_id
            );
        } else {
            $slug = $posted;
            $categorySlug = '';
            if ($request->category_id) {
                $category = \App\Models\Category::find($request->category_id);
                $categorySlug = $category?->slug ?? '';
            }
            if ($categorySlug && !str_starts_with($slug, $categorySlug . '/')) {
                $slug = "{$categorySlug}/{$slug}";
            }
        }

        return $this->generateUniqueSlug($slug, $excludeId);
    }
    private function decodeArrayField($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function sanitizeFaqs(array $faqs): array
    {
        $out = [];
        foreach ($faqs as $row) {
            if (!is_array($row)) {
                continue;
            }
            $q = trim((string) ($row['question'] ?? ''));
            $a = trim((string) ($row['answer'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            $out[] = ['question' => $q, 'answer' => $a];
        }
        return $out;
    }

    private function sanitizeSources(array $sources): array
    {
        $out = [];
        foreach ($sources as $row) {
            if (!is_array($row)) {
                if (is_string($row) && trim($row) !== '') {
                    $out[] = ['title' => trim($row), 'url' => null];
                }
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            if ($title === '' && $url === '') {
                continue;
            }
            $out[] = ['title' => $title, 'url' => $url !== '' ? $url : null];
        }
        return $out;
    }

    private function mergeBlogPayload(Request $request): void
    {
        $cta = trim((string) $request->input('cta_button_url', ''));
        $canonical = trim((string) $request->input('canonical_url', ''));
        $request->merge([
            'faqs' => $this->sanitizeFaqs($this->decodeArrayField($request->input('faqs', []))),
            'sources' => $this->sanitizeSources($this->decodeArrayField($request->input('sources', []))),
            'cta_button_url' => $cta !== '' ? $cta : null,
            'canonical_url' => $canonical !== '' ? $canonical : null,
        ]);
    }

    private function resolveDuration(Request $request, string $content): string
    {
        $posted = trim((string) $request->input('duration', ''));
        if ($posted !== '') {
            return $posted;
        }
        return $this->calculateReadingTime($content);
    }

    public function store(Request $request)
    {
        $this->mergeBlogPayload($request);
        $validated = $request->validate([
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'seo_keywords' => 'nullable|string|max:500',
            'title' => 'required|max:255',
            'audience' => ['required', Rule::in(config('doctors.audiences', ['patients', 'doctors']))],
            'slug' => 'nullable|string|max:255',
            'excerpt' => 'nullable|string|max:2000',
            'content' => 'required',
            'preview_image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:5120',
            'author_id' => 'nullable|exists:users,id',
            'category_id' => 'nullable|exists:categories,id',
            'duration' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'table_of_contents' => 'nullable|string',
            'sources' => 'nullable|array', // Валидируем как массив
            'sources.*.title' => 'nullable|string|max:255',
            'sources.*.url' => 'nullable|url|max:500',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'cta_title' => 'nullable|string|max:255',
            'cta_description' => 'nullable|string|max:500',
            'cta_button_text' => 'nullable|string|max:100',
            'cta_button_url' => 'nullable|url|max:255',
            'is_active' => 'boolean',
            'canonical_url' => 'nullable|url|max:255',
            'related_posts' => 'nullable|array',
            'related_posts.*' => 'exists:blogs,id',
            'faqs' => 'nullable|array',
            'faqs.*.question' => 'required|string|max:500',
            'faqs.*.answer' => 'required|string',
            'noindex' => 'boolean',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
        ]);

        $validated['slug'] = $this->resolveBlogSlug($validated, $request);
        $validated['faqs'] = $request->input('faqs', []);
        $validated['duration'] = $this->resolveDuration($request, $validated['content']);

        // Обработка is_active
        $validated['is_active'] = $request->has('is_active') ? ($request->input('is_active') === '1' || $request->input('is_active') === true) : true;
        $validated['noindex'] = $request->input('noindex') === '1'
            || $request->input('noindex') === 1
            || $request->input('noindex') === true;
        $validated['og_title'] = $request->input('og_title') ?: null;
        $validated['og_description'] = $request->input('og_description') ?: null;

        if ($request->hasFile('preview_image')) {
            $validated['preview_image'] = $this->imageService->storeUploadedImage(
                $request->file('preview_image'),
                'blog/previews',
                \App\Services\ImageService::COVER_QUALITY,
                \App\Services\ImageService::COVER_MAX_EDGE,
                \App\Services\ImageService::COVER_VARIANTS
            );
        }

        $validated['user_id'] = $request->filled('author_id') ? $request->author_id : null;

        // Дата публикации на сайте — только в момент первого выхода (не дата создания черновика).
        $validated['published_at'] = null;
        // Загрузка картинки

        // Генерация содержания
        $result = $this->generateTableOfContents($validated['content']);
        $validated['content'] = $result['content'];
        $validated['table_of_contents'] = json_encode($result['toc'], JSON_UNESCAPED_UNICODE);

        // 🔹 ИСПРАВЛЕНИЕ: Преобразуем массив sources в JSON-строку ПЕРЕД созданием
        if (!empty($validated['sources']) && is_array($validated['sources'])) {
            $validated['sources'] = json_encode($validated['sources'], JSON_UNESCAPED_UNICODE);
        } else {
            $validated['sources'] = null; // Или '[]', если поле не nullable
        }

        // Создаем пост
        $blog = Blog::create($validated);

        if ($blog->is_active) {
            $blog->published_at = now();
            $blog->saveQuietly();
        }

        // Связанные посты
        if (isset($validated['related_posts']) && !empty($validated['related_posts'])) {
            $blog->relatedPosts()->sync($validated['related_posts']);
        }

        // Теги
        if (!empty($validated['tag_ids']) && is_array($validated['tag_ids'])) {
            $blog->tags()->sync($validated['tag_ids']);
        } else {
            $blog->tags()->detach();
        }
        Cache::forget('sitemap');
        return redirect()->route('admin.blog.index')->with('success', 'Блог создан');
    }
    public function edit(Blog $blog)
    {
        // 🔹 Загружаем связи + related_posts
        $blog->load(['author', 'category', 'tags', 'relatedPosts']);

        $authors = \App\Models\User::select('id', 'name')->orderBy('name')->get();
        $categories = \App\Models\Category::select('id', 'name')->orderBy('name')->get();
        $allBlogs = Blog::select('id', 'title', 'slug', 'category_id')
            ->where('id', '!=', $blog->id)
            ->orderBy('title')
            ->get();
        return Inertia::render('Admin/Blog/Edit', [
            'blog' => $blog, // Теперь содержит relatedPosts
            'authors' => $authors,
            'categories' => $categories,
            'tags' => \App\Models\BlogTag::all(),
            'selectedTags' => $blog?->tags->pluck('id')->toArray() ?? [],
            'seo_title' => $blog->seo_title,
            'seo_description' => $blog->seo_description,
            'seo_keywords' => $blog->seo_keywords,
            'is_active' => $blog->is_active,
            'canonical_url' => $blog->canonical_url,
            'allBlogs' => $allBlogs,
            'audienceOptions' => [
                ['value' => 'patients', 'label' => 'Пациенты'],
                ['value' => 'doctors', 'label' => 'Врачи'],
            ],
        ]);
    }

    public function update(Request $request, Blog $blog)
    {

        $this->mergeBlogPayload($request);
        $validated = $request->validate([
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'seo_keywords' => 'nullable|string|max:500',
            'title' => 'required|string|max:255',
            'audience' => ['required', Rule::in(config('doctors.audiences', ['patients', 'doctors']))],
            'excerpt' => 'nullable|string|max:2000',
            'slug' => 'nullable|string|max:255',
            'content' => 'required|string',
            'preview_image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:5120',
            'author_id' => 'nullable|exists:users,id',
            'category_id' => 'nullable|exists:categories,id',
            'duration' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'table_of_contents' => 'nullable|string',
            'sources' => 'nullable|array',
            'sources.*.title' => 'nullable|string|max:255',
            'sources.*.url' => 'nullable|url|max:500',
            'cta_title' => 'nullable|string|max:255',
            'cta_description' => 'nullable|string|max:500',
            'cta_button_text' => 'nullable|string|max:100',
            'cta_button_url' => 'nullable|url|max:255',
            'related_posts' => 'nullable|array',
            'related_posts.*' => 'exists:blogs,id',
            'is_active' => 'boolean',
            'canonical_url' => 'nullable|url|max:255',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'faqs' => 'nullable|array',
            'faqs.*.question' => 'required|string|max:500',
            'faqs.*.answer' => 'required|string',
            'noindex' => 'boolean',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
            'published_at' => 'nullable|date',
        ]);


        $postedSlug = trim((string) ($validated['slug'] ?? ''));
        if ($postedSlug === '' || $postedSlug === (string) $blog->slug) {
            $validated['slug'] = $blog->slug;
        } else {
            $validated['slug'] = $this->resolveBlogSlug($validated, $request, $blog->id);
        }
        $validated['is_active'] = $request->input('is_active') === '1'
            || $request->input('is_active') === 1
            || $request->input('is_active') === true;
        $validated['noindex'] = $request->input('noindex') === '1'
            || $request->input('noindex') === 1
            || $request->input('noindex') === true;
        $validated['og_title'] = $request->input('og_title') ?: null;
        $validated['og_description'] = $request->input('og_description') ?: null;
        $validated['faqs'] = $request->input('faqs', []);
        $validated['canonical_url'] = $request->input('canonical_url') ?: null;
        if (isset($validated['sources']) && is_array($validated['sources'])) {
            $validated['sources'] = json_encode($validated['sources'], JSON_UNESCAPED_UNICODE);
        } else {
            $validated['sources'] = null;
        }

        if (!$request->hasFile('preview_image')) {
            unset($validated['preview_image']);
        }

        if ($request->hasFile('preview_image')) {
            $newPath = $this->imageService->storeUploadedImage(
                $request->file('preview_image'),
                'blog/previews',
                \App\Services\ImageService::COVER_QUALITY,
                \App\Services\ImageService::COVER_MAX_EDGE,
                \App\Services\ImageService::COVER_VARIANTS
            );
            if ($blog->preview_image && $blog->preview_image !== $newPath) {
                $this->imageService->deleteWithVariants($blog->preview_image);
            }
            $validated['preview_image'] = $newPath;
        }

        $validated['duration'] = $this->resolveDuration($request, $validated['content']);
        $validated['user_id'] = $request->filled('author_id') ? $request->author_id : null;

        $result = $this->generateTableOfContents($validated['content']);
        $validated['content'] = $result['content'];
        $validated['table_of_contents'] = json_encode($result['toc'], JSON_UNESCAPED_UNICODE);
        $wasActive = (bool) $blog->is_active;
        $oldSlug = $blog->slug;

        // Заполняем модель новыми данными
        $blog->fill($validated);

        // Дата на сайте = момент публикации (вкл. «Активен»), не дата создания в админке.
        if ($blog->is_active) {
            if ($request->filled('published_at')) {
                $blog->published_at = AdminPublishedAt::parse($request->input('published_at'));
            } elseif (! $wasActive || empty($blog->published_at)) {
                $blog->published_at = now();
            }
        }

        // Сохраняем изменения
        $blog->save();

        $newSlug = $blog->slug;
        if ($oldSlug && $newSlug && $oldSlug !== $newSlug) {
            $from = Redirect::normalizeUrl('/blog/' . ltrim($oldSlug, '/'));
            $to = Redirect::normalizeUrl('/blog/' . ltrim($newSlug, '/'));
            if ($from !== $to) {
                $flatTo = Redirect::flattenToUrl($to, $from);
                Redirect::updateOrCreate(
                    ['from_url' => $from],
                    [
                        'to_url' => $flatTo,
                        'status_code' => 301,
                        'is_active' => true,
                    ]
                );
            }
        }

        if (isset($validated['related_posts'])) {
            $blog->relatedPosts()->sync($validated['related_posts']);
        }
        if (!empty($validated['tag_ids']) && is_array($validated['tag_ids'])) {
            $blog->tags()->sync($validated['tag_ids']);
        } else {
            $blog->tags()->detach();
        }
        Cache::forget('sitemap');
        return redirect()->route('admin.blog.index')->with('success', 'Пост обновлён!');
    }

    public function destroy(Blog $blog)
    {
        $blog->delete(); // Теперь это soft delete
        Cache::forget('sitemap');
        return redirect()->route('admin.blog.index')->with('success', 'Пост перемещён в корзину');
    }
    public function restore($id)
    {
        $blog = Blog::onlyTrashed()->findOrFail($id);
        $blog->restore();
        Cache::forget('sitemap');
        return redirect()->route('admin.blog.trashed')->with('success', 'Пост восстановлен');
    }

// 🔹 НОВЫЙ: Окончательное удаление
    public function forceDelete($id)
    {
        $blog = Blog::onlyTrashed()->findOrFail($id);

        // Удаляем картинку
        if ($blog->preview_image && Storage::disk('public')->exists($blog->preview_image)) {
            Storage::disk('public')->delete($blog->preview_image);
        }

        $blog->forceDelete();
        return redirect()->route('admin.blog.trashed')->with('success', 'Пост удалён окончательно');
    }

    private function generateUniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $originalSlug = $slug;
        $count = 1;

        while (true) {
            $query = Blog::where('slug', $slug);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if (!$query->exists()) {
                return $slug;
            }

            // Добавляем счётчик в конец (после последнего слэша, если есть)
            if (str_contains($originalSlug, '/')) {
                $parts = explode('/', $originalSlug);
                $lastPart = array_pop($parts);
                $slug = implode('/', $parts) . '/' . $lastPart . '-' . $count;
            } else {
                $slug = $originalSlug . '-' . $count;
            }
            $count++;
        }
    }

}