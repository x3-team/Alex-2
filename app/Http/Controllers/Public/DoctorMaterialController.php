<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\DoctorMaterialsStore;
use Inertia\Inertia;
use Inertia\Response;

class DoctorMaterialController extends Controller
{
    public function index()
    {
        return app(BlogController::class)->index(request());
    }

    public function documents(): Response
    {
        $store = new DoctorMaterialsStore();
        $store->ensureDefaultCategory();

        return Inertia::render('Public/DoctorMaterials', [
            'view' => 'documents',
            'feed' => [],
            'categories' => $store->publicCategories(),
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
            $store->publicCategories(),
            fn ($item) => $item['id'] !== $category['id']
        ));

        return Inertia::render('Public/DoctorMaterialCategory', [
            'category' => $category,
            'materials' => $files,
            'otherCategories' => $others,
            'seoMeta' => $this->seoMeta(),
        ]);
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
