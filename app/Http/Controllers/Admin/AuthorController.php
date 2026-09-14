<?php

namespace App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuthorCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use App\Services\ImageService;
class AuthorController extends Controller
{
    protected ImageService $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function index()
    {

        $authors = User::with(['authorCategories'])
            ->latest()
            ->paginate(15);

        $pageDescription = \App\Models\Setting::firstWhere('key', 'authors_page_description')?->value ?? '';

        return Inertia::render('Admin/Authors/Index', [
            'authors' => $authors,
            'authorsPageDescription' => $pageDescription,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Authors/Create', [
            'authorCategories' => AuthorCategory::all()
        ]);
    }

    public function store(Request $request)
    {
        // 1. Сначала получаем сырые данные career_history и education
        $rawCareer = $request->input('career_history');
        $rawEducation = $request->input('education');

        // 2. Декодируем их, если они пришли как строки (из Vue JSON.stringify)
        $careerHistory = is_string($rawCareer) ? json_decode($rawCareer, true) : $rawCareer;
        $education = is_string($rawEducation) ? json_decode($rawEducation, true) : $rawEducation;

        // Гарантируем, что это массивы
        if (!is_array($careerHistory)) $careerHistory = [];
        if (!is_array($education)) $education = [];

        // 3. ВАЛИДАЦИЯ (используем уже декодированные данные для проверки логики,
        // но саму валидацию request запускаем по исходным данным, заменив типы на string)

        // Лучше всего временно заменить данные в запросе, чтобы валидация прошла корректно,
        // если мы хотим валидировать структуру массива.
        // Но проще валидировать как string, а потом проверять структуру вручную.

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:2048',
            'bio' => 'nullable|string|max:500',
            'is_admin' => 'boolean',
            'phone' => 'nullable|string|max:30',
            // Валидируем как строку, так как Vue присылает JSON строку
            'career_history' => 'nullable|string',
            'education' => 'nullable|string',
            'author_categories' => 'nullable|array',
            'author_categories.*' => 'exists:author_categories,id',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'seo_keywords' => 'nullable|string|max:255',
            'credentials' => 'nullable|string|max:255',
        ]);

        // 4. Ручная валидация структуры decoded массивов
        foreach ($careerHistory as $index => $entry) {
            if (!is_array($entry)) continue;
            $yearFrom = $entry['year_from'] ?? null;
            $yearTo = $entry['year_to'] ?? null;
            if ($yearFrom && $yearTo && $yearTo < $yearFrom) {
                return back()->withErrors([
                    "career_history" => "Запись #$index: Год окончания не может быть раньше года начала"
                ])->withInput();
            }
        }

        foreach ($education as $index => $entry) {
            if (!is_array($entry)) continue;
            $yearFrom = $entry['year_from'] ?? null;
            $yearTo = $entry['year_to'] ?? null;
            if ($yearFrom && $yearTo && $yearTo < $yearFrom) {
                return back()->withErrors([
                    "education" => "Запись #$index: Год окончания не может быть раньше года начала"
                ])->withInput();
            }
        }

        // 5. Обработка аватара
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $this->imageService->storeUploadedImage(
                $request->file('avatar'),
                'authors/avatars',
                \App\Services\ImageService::AVATAR_QUALITY,
                \App\Services\ImageService::AVATAR_MAX_EDGE,
                \App\Services\ImageService::AVATAR_VARIANTS
            );
        }

        // 6. Подготовка данных для сохранения
        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'bio' => $validated['bio'],
            'seo_title' => $validated['seo_title'],
            'seo_description' => $validated['seo_description'],
            'seo_keywords' => $validated['seo_keywords'],
            'avatar' => $avatarPath,
            'is_admin'=> $request->boolean('is_admin'),
            'phone' => $validated['phone'] ?? null,
            // Сохраняем как JSON строку в базу (если у вас в модели нет cast 'array')
            // Если в модели User есть protected $casts = ['career_history' => 'array'], то сохраняйте массив
            // Обычно в Laravel лучше хранить JSON и использовать casts.
            'career_history' => $careerHistory,
            'education' => $education,
            'credentials' => $validated['credentials'] ?? null,
        ];

        // 7. Создание пользователя
        $user = User::create($userData);

        // 8. Синхронизация категорий
        if ($request->filled('author_categories')) {
            $user->authorCategories()->sync($request->input('author_categories'));
        }

        return redirect()->route('admin.authors.index')->with('success', 'Автор создан!');
    }

    public function edit(User $author)  // 🔹 Параметр $user (или $author)
    {

        return Inertia::render('Admin/Authors/Edit', [
            // 🔹 Ключ 'author' — массив с данными, НЕ сама модель!
            'author' => [
                'id' => $author->id,
                'name' => $author->name,
                'email' => $author->email,
                'avatar' => $author->avatar,
                'bio' => $author->bio,
                'phone' => $author->phone,
                'is_admin' => $author->is_admin ?? false,
                'education' => $author->education,
                'career_history' => $author->career_history,
                'seo_title' => $author->seo_title,
                'seo_description' => $author->seo_description,
                'seo_keywords' => $author->seo_keywords,
                'credentials' => $author->credentials,
            ],
            'selectedCategories' => $author->authorCategories->pluck('id')->toArray(),
            'authorCategories' => \App\Models\AuthorCategory::all(),
        ]);
    }

    public function update(Request $request, User $author)
    {
        $passwordSent = $request->filled('password');
        if (!$passwordSent) {
            $request->request->remove('password');
            $request->request->remove('password_confirmation');
        }

        // 🔹 Декодируем career_history
        if ($request->has('career_history') && is_string($request->career_history)) {
            try {
                $decoded = json_decode($request->career_history, true);
                if (is_array($decoded)) {
                    $request->merge(['career_history' => $decoded]);
                }
            } catch (\Exception $e) {}
        }

        // 🔹 Декодируем education
        if ($request->has('education') && is_string($request->education)) {
            try {
                $decoded = json_decode($request->education, true);
                if (is_array($decoded)) {
                    $request->merge(['education' => $decoded]);
                }
            } catch (\Exception $e) {}
        }

        // 🔹 Валидация
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => $passwordSent ? 'required|min:8|confirmed' : 'nullable',
            'bio' => 'nullable|string',
            'career_history' => 'nullable|array',
            'career_history.*.year_from' => 'nullable|integer|min:1950|max:' . date('Y'),
            'career_history.*.year_to' => 'nullable|integer|min:1950|max:' . date('Y'),
            'career_history.*.place' => 'nullable|string',
            'education' => 'nullable|array',
            'phone' => 'nullable|string|max:30',
            'education.*.year_from' => 'nullable|integer|min:1950|max:' . date('Y'),
            'education.*.year_to' => 'nullable|integer|min:1950|max:' . date('Y'),
            'education.*.place' => 'nullable|string',
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:2048',
            'author_categories' => 'nullable|array',
            'author_categories.*' => 'exists:author_categories,id',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'seo_keywords' => 'nullable|string|max:255',
            'is_admin' => 'boolean',
            'credentials' => 'nullable|string|max:255',
        ]);

        // 🔹 Валидация year_to >= year_from
        $careerHistory = $validated['career_history'] ?? [];
        foreach ($careerHistory as $index => $entry) {
            $yearFrom = $entry['year_from'] ?? null;
            $yearTo = $entry['year_to'] ?? null;
            if ($yearFrom && $yearTo && $yearTo < $yearFrom) {
                return back()->withErrors([
                    "career_history.{$index}.year_to" => "Год окончания не может быть раньше года начала"
                ]);
            }
        }

        $educationData = $validated['education'] ?? [];
        foreach ($educationData as $index => $entry) {
            $yearFrom = $entry['year_from'] ?? null;
            $yearTo = $entry['year_to'] ?? null;
            if ($yearFrom && $yearTo && $yearTo < $yearFrom) {
                return back()->withErrors([
                    "education.{$index}.year_to" => "Год окончания не может быть раньше года начала"
                ]);
            }
        }
        $validated['is_admin'] = $request->boolean('is_admin');
        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $cleanCareer = array_filter($careerHistory, fn($e) => !empty($e['year_from']) || !empty($e['place']));
        $cleanEducation = array_filter($educationData, fn($e) => !empty($e['year_from']) || !empty($e['place']));

        try {
            $author->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'bio' => $validated['bio'] ?? $author->bio,
                'career_history' => array_values($cleanCareer),
                'is_admin' => $validated['is_admin'] ?? false,
                'education' => array_values($cleanEducation),
                'seo_title' => $validated['seo_title'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
                'seo_keywords' => $validated['seo_keywords'] ?? null,
                'credentials' => $validated['credentials'] ?? null,
            ]);

            if ($request->filled('author_categories')) {
                $author->authorCategories()->sync($request->author_categories);
            } else {
                $author->authorCategories()->detach();
            }

            if ($request->hasFile('avatar')) {
                $newPath = $this->imageService->storeUploadedImage(
                    $request->file('avatar'),
                    'authors/avatars',
                    \App\Services\ImageService::AVATAR_QUALITY,
                    \App\Services\ImageService::AVATAR_MAX_EDGE,
                    \App\Services\ImageService::AVATAR_VARIANTS
                );
                if ($author->avatar && $author->avatar !== $newPath) {
                    $this->imageService->deleteWithVariants($author->avatar);
                }
                $author->update(['avatar' => $newPath]);
            } elseif ($request->input('delete_avatar') === '1') {
                if ($author->avatar) {
                    $this->imageService->deleteWithVariants($author->avatar);
                }
                $author->update(['avatar' => null]);
            }

            return redirect()->route('admin.authors.index')->with('success', 'Автор обновлён!');

        } catch (\Exception $e) {
            Log::error('Author update failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Ошибка: ' . $e->getMessage());
        }
    }

    public function destroy(User $author)
    {
        if ($author->id === Auth::id()) {
            return back()->with('error', 'Нельзя удалить свой аккаунт');
        }

        $author->delete();
        return redirect()->route('admin.authors.index')->with('success', 'Автор удалён!');
    }
    public function author(Request $request, User $author)
    {
        $query = Blog::where('user_id', $author->id)
            ->with(['author', 'category', 'tags'])
            ->whereNotNull('published_at')
            ->where('is_active', true);

        // 🔹 Фильтр по категории
        if ($request->filled('category')) {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // 🔹 Фильтр по тегам
        if ($request->filled('tags')) {
            $tagSlugs = array_filter(explode(',', $request->tags));
            if (count($tagSlugs) > 0) {
                $query->whereHas('tags', function($q) use ($tagSlugs) {
                    $q->whereIn('slug', $tagSlugs);
                });
            }
        }

        $blogs = $query->latest('published_at')->paginate(10)->withQueryString();

        // 🔹 🔥 ОТЛАДКА: проверяем публикации автора
        $allAuthorBlogs = Blog::where('user_id', $author->id)
            ->whereNotNull('published_at')
            ->where('is_active', true)
            ->with('tags')
            ->get();

        // 🔹 🔥 ОТЛАДКА: выводим в лог
        \Log::info('Автор ID: ' . $author->id);
        \Log::info('Публикаций автора: ' . $allAuthorBlogs->count());
        \Log::info('Публикации с тегами: ' . $allAuthorBlogs->filter(fn($b) => $b->tags->count() > 0)->count());

        $tagIds = $allAuthorBlogs->pluck('tags')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->filter();

        \Log::info('ID тегов: ' . $tagIds->implode(', '));

        $tags = \App\Models\BlogTag::whereIn('id', $tagIds)
            ->orderBy('name')
            ->get();

        \Log::info('Тегов найдено: ' . $tags->count());

        // 🔹 Категории
        $categoryIds = $allAuthorBlogs->pluck('category_id')
            ->unique()
            ->filter();

        $categories = \App\Models\Category::whereIn('id', $categoryIds)
            ->orderBy('name')
            ->get();
        $authorData = $author->toArray();
        $authorData['author_categories'] = $author->authorCategories->toArray();

        // Гарантируем что education передаётся
        $authorData['education'] = $author->education ?? null;
        $authorData['career_history'] = $author->career_history ?? null;
        return Inertia::render('Public/Blog/Author', [
            'author' => $authorData,
            'blogs' => $blogs,
            'categories' => $categories,
            'tags' => $tags,
            'filters' => [
                'category' => $request->category,
                'tags' => $request->tags,
            ],
        ]);
    }
}