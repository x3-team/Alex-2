<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
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

        $categories = $store->categoriesWithCounts();
        $files = $store->files();

        return Inertia::render('Public/DoctorMaterials', [
            'categories' => $categories,
            'materials' => $files,
            'seoMeta' => $this->seoMeta(),
        ]);
    }

    public function category(string $categorySlug): Response
    {
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

    private function seoMeta(): array
    {
        return [
            'title' => Setting::get('doctor_materials_meta_title', 'Документы для врачей — ALEX LAB'),
            'description' => Setting::get('doctor_materials_meta_description', 'Регистрационные документы, инструкции и бланки лаборатории.'),
            'keywords' => Setting::get('doctor_materials_meta_keywords', ''),
            'noindex' => true,
        ];
    }
}
