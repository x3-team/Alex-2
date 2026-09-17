<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\DoctorMaterialsStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DoctorMaterialsController extends Controller
{
    public function index()
    {
        $store = new DoctorMaterialsStore();

        return Inertia::render('Admin/DoctorMaterials/index', [
            'categories' => $store->categories(),
            'materials' => $store->files(),
            'seoMeta' => [
                'title' => Setting::get('doctor_materials_meta_title', ''),
                'description' => Setting::get('doctor_materials_meta_description', ''),
                'keywords' => Setting::get('doctor_materials_meta_keywords', ''),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'categories' => 'present|array|max:'.DoctorMaterialsStore::MAX_CATEGORIES,
            'categories.*.id' => 'nullable|string|max:64',
            'categories.*.name' => 'required|string|max:255',
            'categories.*.slug' => 'nullable|string|max:255',
            'categories.*.description' => 'nullable|string|max:500',
            'materials' => 'present|array',
            'materials.*.id' => 'nullable|string|max:64',
            'materials.*.title' => 'required|string|max:255',
            'materials.*.file_path' => 'nullable|string|max:500',
            'materials.*.date' => 'nullable|string|max:64',
            'materials.*.description' => 'nullable|string|max:500',
            'materials.*.category_id' => 'nullable|string|max:64',
            'materials.*.sort_order' => 'nullable|integer|min:0|max:9999',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:500',
        ]);

        $usedSlugs = [];
        $categories = array_map(function (array $row) use (&$usedSlugs) {
            $name = trim($row['name']);
            $id = trim((string) ($row['id'] ?? '')) ?: (string) Str::uuid();
            $slug = Str::slug($row['slug'] ?: $name, '-', 'ru') ?: 'dokumenty';
            $base = $slug;
            $i = 2;
            while (in_array($slug, $usedSlugs, true)) {
                $slug = $base.'-'.$i;
                $i++;
            }
            $usedSlugs[] = $slug;

            return [
                'id' => $id,
                'name' => $name,
                'slug' => $slug,
                'description' => trim((string) ($row['description'] ?? '')),
            ];
        }, array_slice($validated['categories'], 0, DoctorMaterialsStore::MAX_CATEGORIES));

        $categoryIds = array_column($categories, 'id');
        $perCategory = [];
        $files = array_map(function (array $row) use ($categoryIds, &$perCategory) {
            $categoryId = trim((string) ($row['category_id'] ?? ''));
            if ($categoryId !== '' && ! in_array($categoryId, $categoryIds, true)) {
                $categoryId = $categoryIds[0] ?? '';
            }

            $bucket = $categoryId !== '' ? $categoryId : '_none';
            $perCategory[$bucket] = ($perCategory[$bucket] ?? 0) + 1;

            return [
                'id' => trim((string) ($row['id'] ?? '')) ?: (string) Str::uuid(),
                'title' => trim($row['title']),
                'file_path' => trim((string) ($row['file_path'] ?? '')),
                'date' => trim((string) ($row['date'] ?? '')),
                'description' => trim((string) ($row['description'] ?? '')),
                'category_id' => $categoryId,
                'sort_order' => $perCategory[$bucket],
            ];
        }, $validated['materials']);

        (new DoctorMaterialsStore())->save($categories, $files);

        Setting::set('doctor_materials_meta_title', $validated['meta_title'] ?? '');
        Setting::set('doctor_materials_meta_description', $validated['meta_description'] ?? '');
        Setting::set('doctor_materials_meta_keywords', $validated['meta_keywords'] ?? '');

        return back()->with('success', 'Категории, документы и SEO сохранены.');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store('doctor-materials', 'public');

        return response()->json([
            'path' => Storage::url($path),
            'original_name' => $file->getClientOriginalName(),
        ], 200, [
            'Content-Type' => 'application/json',
            'X-Inertia' => 'false',
        ]);
    }
}
