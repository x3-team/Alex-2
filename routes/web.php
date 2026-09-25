<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\AuthorController;
use App\Http\Controllers\Admin\AuthorCategoryController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Admin\BlogTagController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\SettingController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\AlexLabController as AdminAlexLabController;
use App\Http\Controllers\Public\AlexLabController as PublicAlexLabController;
use App\Http\Controllers\Public\BlogController as PublicBlogController;
use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Public\QuizController;
use App\Http\Controllers\Admin\DemoResultController as AdminDemoResultController;
use App\Http\Controllers\Public\DemoResultController as PublicDemoResultController;
use App\Http\Controllers\Public\DoctorMaterialController;
use App\Http\Controllers\Doctors\DoctorMaterialsController;
use App\Http\Controllers\Admin\AllergenController as AdminAllergenController;
use App\Http\Controllers\Admin\OrderController;


// === ГЛАВНАЯ + AUTH только на patient apex (doc.* served by DoctorsRouteServiceProvider) ===
$apexHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
Route::domain($apexHost)->group(function () {
    Route::get('/', [\App\Http\Controllers\Public\HomeController::class, 'index'])->name('home');
    require __DIR__.'/auth.php';
});

//Route::prefix('admin')->name('admin.')->group(function () {
//    Route::get('/login', [\App\Http\Controllers\Admin\AuthController::class, 'showLoginForm'])->name('login');
//    Route::post('/login', [\App\Http\Controllers\Admin\AuthController::class, 'login'])->name('login.post');
//});

// === АДМИНКА ===
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    // Дашборд
    Route::get('/', function () {
        return Inertia::render('Dashboard', [
            'auth' => ['user' => Auth::user()]
        ]);
    })->name('home');

    // Загрузка изображений
    Route::post('/upload-image', function (Request $request) {
        $request->validate(['file' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:5120']);

        $path = app(\App\Services\ImageService::class)->storeUploadedImage(
            $request->file('file'),
            'blog/images',
            \App\Services\ImageService::CONTENT_QUALITY,
            \App\Services\ImageService::CONTENT_MAX_EDGE
        );
        $url = Storage::url($path);

        return response()->json(['url' => $url], 200, [
            'Content-Type' => 'application/json',
            'X-Inertia' => 'false'
        ]);
    })->name('upload-image');

    // Ресурсы
    Route::resource('blog-tags', BlogTagController::class)->except(['show']);
    Route::resource('author-categories', AuthorCategoryController::class);
    Route::resource('categories', CategoryController::class);

    // МАРШРУТЫ КОРЗИНЫ
    Route::get('/blog/trashed', [BlogController::class, 'trashed'])->name('blog.trashed');
    Route::post('/blog/{id}/restore', [BlogController::class, 'restore'])->name('blog.restore');
    Route::delete('/blog/{id}/force-delete', [BlogController::class, 'forceDelete'])->name('blog.force-delete');

    Route::get('/home', [HomeController::class, 'edit'])->name('home.edit');
    Route::put('/home', [HomeController::class, 'update'])->name('home.update');

    // Настройки корзины
    Route::get('/cart-settings', [\App\Http\Controllers\Admin\CartSettingController::class, 'edit'])->name('cart-settings.edit');
    Route::put('/cart-settings', [\App\Http\Controllers\Admin\CartSettingController::class, 'update'])->name('cart-settings.update');

    Route::get('/quiz', [\App\Http\Controllers\Admin\QuizController::class, 'index'])->name('quiz.index');
    Route::post('/quiz', [\App\Http\Controllers\Admin\QuizController::class, 'store'])->name('quiz.store');


    Route::get('/allergens', [AdminAllergenController::class, 'index'])->name('allergens.index');
    Route::post('/allergens', [AdminAllergenController::class, 'store'])->name('allergens.store');
    Route::put('/allergens/{allergen}', [AdminAllergenController::class, 'update'])->name('allergens.update');
    Route::delete('/allergens/{allergen}', [AdminAllergenController::class, 'destroy'])->name('allergens.destroy');
    // Заявки

        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/export', [OrderController::class, 'export'])->name('orders.export');
        Route::put('/orders/mail-settings', [OrderController::class, 'updateMailSettings'])->name('orders.mail-settings');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');


    Route::get('/robots', [\App\Http\Controllers\Admin\AdminRobotsController::class, 'index'])
        ->name('robots.index');
    Route::post('/robots', [\App\Http\Controllers\Admin\AdminRobotsController::class, 'update'])
        ->name('robots.update');

    // Редиректы
    Route::get('/redirects', [\App\Http\Controllers\Admin\RedirectController::class, 'index'])
        ->name('redirects.index');
    Route::get('/redirects/create', [\App\Http\Controllers\Admin\RedirectController::class, 'create'])
        ->name('redirects.create');
    Route::post('/redirects', [\App\Http\Controllers\Admin\RedirectController::class, 'store'])
        ->name('redirects.store');
    Route::get('/redirects/{redirect}/edit', [\App\Http\Controllers\Admin\RedirectController::class, 'edit'])
        ->name('redirects.edit');
    Route::put('/redirects/{redirect}', [\App\Http\Controllers\Admin\RedirectController::class, 'update'])
        ->name('redirects.update');
    Route::delete('/redirects/{redirect}', [\App\Http\Controllers\Admin\RedirectController::class, 'destroy'])
        ->name('redirects.destroy');
    Route::post('/redirects/{redirect}/toggle', [\App\Http\Controllers\Admin\RedirectController::class, 'toggle'])
        ->name('redirects.toggle');

    Route::resource('blog', BlogController::class);

    // Авторы
    Route::resource('authors', AuthorController::class)->except(['show'])->parameters(['authors' => 'author']);

    // Обновление описания страницы авторов
    Route::post('/authors/update-seo', function (Request $request) {
        $request->validate([
            'authors_page_description' => 'nullable|string|max:1000',
            'authors_page_meta_title' => 'nullable|string|max:255',
            'authors_page_meta_description' => 'nullable|string|max:500',
            'authors_page_meta_keywords' => 'nullable|string|max:500',
        ]);

        \App\Models\Setting::updateOrCreate(
            ['key' => 'authors_page_description'],
            ['value' => $request->authors_page_description]
        );

        \App\Models\Setting::updateOrCreate(
            ['key' => 'authors_page_meta_title'],
            ['value' => $request->authors_page_meta_title]
        );

        \App\Models\Setting::updateOrCreate(
            ['key' => 'authors_page_meta_description'],
            ['value' => $request->authors_page_meta_description]
        );

        \App\Models\Setting::updateOrCreate(
            ['key' => 'authors_page_meta_keywords'],
            ['value' => $request->authors_page_meta_keywords]
        );

        return redirect()->back()->with('success', 'SEO настройки сохранены!');
    })->name('authors.update-seo');

    Route::post('/authors/update-description', function (Request $request) {
        $request->validate([
            'authors_page_description' => 'nullable|string|max:1000'
        ]);

        \App\Models\Setting::updateOrCreate(
            ['key' => 'authors_page_description'],
            ['value' => $request->authors_page_description]
        );

        return redirect()->back()->with('success', 'Описание обновлено!');
    })->name('authors.update-description');

    // Настройки блога
    Route::get('/blog/settings', [SettingController::class, 'blogPage'])->name('blog.settings');
    Route::post('/blog/settings', [SettingController::class, 'updateBlogPage'])->name('blog.settings.update');

    // AJAX для фильтрации постов по категории в админке
    Route::get('/blog/posts-by-category/{category}', function ($categoryId, Request $request) {
        $exclude = $request->query('exclude');

        $posts = \App\Models\Blog::with(['author', 'category'])
            ->where('category_id', $categoryId)
            ->where('id', '!=', $exclude)
            ->whereNotNull('published_at')
            ->select('id', 'title', 'slug', 'excerpt')
            ->get();

        return response()->json(['posts' => $posts]);
    })->name('blog.posts-by-category');

    // Управление страницами ALEX LAB (Админка)
    Route::get('/alex-lab', [AdminAlexLabController::class, 'index'])->name('alex-lab.index');
    Route::put('/alex-lab', [AdminAlexLabController::class, 'update'])->name('alex-lab.update');
    Route::post('/alex-lab/upload-license', [AdminAlexLabController::class, 'uploadLicense'])->name('alex-lab.upload-license');

    // Демо-результат (Админка)
    Route::get('/demo-result', [AdminDemoResultController::class, 'index'])->name('demo-result.index');
    Route::post('/demo-result', [AdminDemoResultController::class, 'update'])->name('demo-result.update');

    // Материалы для врачей (Админка)
    Route::get('/doctor-materials', [\App\Http\Controllers\Admin\DoctorMaterialsController::class, 'index'])->name('doctor-materials.index');
    Route::put('/doctor-materials', [\App\Http\Controllers\Admin\DoctorMaterialsController::class, 'update'])->name('doctor-materials.update');
    Route::post('/doctor-materials/upload', [\App\Http\Controllers\Admin\DoctorMaterialsController::class, 'upload'])->name('doctor-materials.upload');

    Route::get('/doctor-videos', [\App\Http\Controllers\Admin\DoctorVideosController::class, 'index'])->name('doctor-videos.index');
    Route::post('/doctor-videos', [\App\Http\Controllers\Admin\DoctorVideosController::class, 'store'])->name('doctor-videos.store');
    Route::put('/doctor-videos/{doctorVideo}', [\App\Http\Controllers\Admin\DoctorVideosController::class, 'update'])->name('doctor-videos.update');
    Route::delete('/doctor-videos/{doctorVideo}', [\App\Http\Controllers\Admin\DoctorVideosController::class, 'destroy'])->name('doctor-videos.destroy');
    Route::post('/doctor-videos/upload', [\App\Http\Controllers\Admin\DoctorVideosController::class, 'upload'])->name('doctor-videos.upload');


});


Route::get('/search', [\App\Http\Controllers\Public\SearchController::class, 'index'])->name('search.index');
Route::redirect('/alex-lab/search', '/search', 301);
Route::get('/cart', [\App\Http\Controllers\Public\CartController::class, 'index'])->name('cart.index');

// Блог
Route::get('/blog', [PublicBlogController::class, 'index'])->name('blog.index');
Route::get('/blog/authors', [PublicBlogController::class, 'authors'])->name('blog.authors');
Route::get('/blog/author/{id}', [PublicBlogController::class, 'author'])->name('blog.author');
Route::post('/blog/{blog}/rate', [PublicBlogController::class, 'rate'])->name('blog.rate');

// Лаборатория ALEX LAB (Публичные маршруты)
Route::get('/alex-lab', [PublicAlexLabController::class, 'index'])->name('alex-lab');

// 🔹 Секции ALEX LAB (licenses|doctors|contacts|privacy). /about → 301 на /alex-lab
Route::redirect('/alex-lab/about', '/alex-lab', 301);
// Канонический consent — /consent (в sitemap/nav); дубль /alex-lab/consent → 301
Route::redirect('/alex-lab/consent', '/consent', 301);
Route::get('/alex-lab/{section}', [PublicAlexLabController::class, 'section'])
    ->where('section', 'licenses|doctors|contacts|privacy')
    ->name('alex-lab.section');

// Обратная совместимость для старых URL
Route::redirect('/privacy-policy', '/alex-lab/privacy', 301)->name('privacy-policy');
Route::get('/consent', [PublicAlexLabController::class, 'consent'])->name('consent');

// Квиз
Route::get('/quiz', [\App\Http\Controllers\Public\QuizController::class, 'index'])->name('quiz.index');
Route::post('/api/quiz/calculate', [\App\Http\Controllers\Public\QuizController::class, 'calculate'])->name('quiz.calculate');

// Материалы и демо-результаты
Route::get('/doctor-materials', [DoctorMaterialsController::class, 'legacy'])->name('doctor-materials.index');
Route::get('/demo-result', [\App\Http\Controllers\Public\DemoResultController::class, 'index'])->name('public.demo-result');

// Авторизация пациентов
Route::get('/patient/login', [\App\Http\Controllers\Public\PatientAuthController::class, 'showLogin'])->name('patient.login');
Route::post('/patient/login', [\App\Http\Controllers\Public\PatientAuthController::class, 'login'])->name('patient.login.post');
Route::get('/recover', [\App\Http\Controllers\Public\PatientAuthController::class, 'showRecover'])->name('patient.recover');
Route::post('/recover', [\App\Http\Controllers\Public\PatientAuthController::class, 'recover'])->name('patient.recover.post');

Route::middleware(['auth', 'doctor.not-patient'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Public\PatientDashboardController::class, 'index'])->name('patient.dashboard');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return Inertia::location(route('patient.login'));
    })->name('logout');

    Route::post('/appointment/cancel', [\App\Http\Controllers\Public\PatientDashboardController::class, 'cancelAppointment'])->name('appointment.cancel');

    Route::get('/profile', [\App\Http\Controllers\Public\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [\App\Http\Controllers\Public\ProfileController::class, 'update'])->name('profile.update');
});


// Разводящая категории: /blog/vidy-allergii (до catch-all статьи)
Route::get('/blog/{categorySlug}', [PublicBlogController::class, 'category'])
    ->where('categorySlug', '^(?!authors$)(?!author$)[A-Za-z0-9_-]+$')
    ->name('blog.category');

// Просмотр конкретного поста блога (В самом конце, чтобы не перехватывал остальные роуты)
Route::get('/blog/{slug}', [PublicBlogController::class, 'show'])
    ->where('slug', '^(?!admin(?:/|$))[\w\-\/]+$')
    ->name('blog.show');


Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])
    ->name('sitemap');

Route::get('/robots.txt', function () {
    $detect = \App\Services\DetectSite::make();
    $sitemapBase = $detect->isDoctorsHost()
        ? rtrim(\App\Support\SeoOrigin::make()->siteBaseUrl(), '/')
        : rtrim((string) config('app.url'), '/');
    $cacheKey = 'robots_txt_' . md5($sitemapBase);

    $content = \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () use ($sitemapBase) {
        $robots = \App\Models\Setting::get(
            \App\Http\Controllers\Admin\AdminRobotsController::ROBOTS_KEY,
            "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /admin/\nDisallow: /login\nDisallow: /patient\nDisallow: /up\n\nSitemap: {$sitemapBase}/sitemap.xml"
        );

        return str_replace('{{sitemap_url}}', $sitemapBase . '/sitemap.xml', $robots);
    });

    return response($content, 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
});