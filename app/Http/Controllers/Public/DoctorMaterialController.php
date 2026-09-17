<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Setting;
use App\Support\DoctorMaterialsStore;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DoctorMaterialController extends Controller
{
    public function index()
    {
        return app(BlogController::class)->index(request());
    }

    public function documents(): RedirectResponse
    {
        return redirect()->to('/materials?type=documents', 301);
    }

    public function category(string $categorySlug)
    {
        $articleExists = Blog::query()
            ->forCurrentSite()
            ->where('slug', $categorySlug)
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->exists();

        if ($articleExists) {
            return app(BlogController::class)->show($categorySlug);
        }

        $store = new DoctorMaterialsStore();
        $store->ensureDefaultCategory();

        $category = $store->categoryBySlug($categorySlug);
        if (! $category) {
            abort(404);
        }

        $files = $store->filesForCategory($category['id']);
        $others = array_values(array_filter(
            $store->publicCategories(),
            fn ($item) => $item['id'] !== $category['id']
        ));

        return Inertia::render('Public/DoctorMaterialCategory', [
            'category' => $category,
            'materials' => $files,
            'otherCategories' => $others,
            'seoMeta' => [
                'title' => $category['name'].' — документы ALEX LAB',
                'description' => $category['description'] ?: Setting::get('doctor_materials_meta_description', 'Регистрационные документы, инструкции и бланки лаборатории.'),
                'keywords' => Setting::get('doctor_materials_meta_keywords', ''),
                'noindex' => true,
            ],
        ]);
    }
}
