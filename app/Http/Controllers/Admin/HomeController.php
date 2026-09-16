<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeSetting;
use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function edit()
    {
        $patientSettings = HomeSetting::getSettings('patient');
        $doctorSettings  = HomeSetting::getSettings('doctor');

        $blogs = Blog::where('is_active', true)
            ->select('id', 'title')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Admin/Home/Edit', [
            'patientSettings' => $patientSettings,
            'doctorSettings'  => $doctorSettings,
            'blogs'           => $blogs,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:patient,doctor',

            // SEO
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords'    => 'nullable|string|max:500',

            // Контент
            'advantages'               => 'nullable|array|max:5',
            'advantages.*.title'       => 'nullable|string|max:255',
            'advantages.*.description' => 'nullable|string',

            'results'               => 'nullable|array|max:3',
            'results.*.title'       => 'nullable|string|max:255',
            'results.*.description' => 'nullable|string',

            // Блок «Как сдать тест»
            'how_to_pass'               => 'nullable|array|max:3',
            'how_to_pass.*.image'       => 'nullable', // Может быть как объектом файла, так и строкой URL
            'how_to_pass.*.title'       => 'nullable|string|max:255',
            'how_to_pass.*.description' => 'nullable|string',

            'faq'            => 'nullable|array',
            'faq.*.question' => 'nullable|string|max:255',
            'faq.*.answer'   => 'nullable|string',

            'featured_blog_ids'   => 'nullable|array|max:2',
            'featured_blog_ids.*' => 'nullable|integer|exists:blogs,id',

            'hero_title'    => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:500',
            'why_subtitle'  => 'nullable|string|max:500',
            'cta_text'      => 'nullable|string|max:255',
            'cta_url'       => 'nullable|string|max:500',
        ]);

        $type = $validated['type'];
        unset($validated['type']);

        $settings = HomeSetting::getSettings($type);
        $existingHowToPass = $settings->how_to_pass ?? [];

        // Обработка файлов для блока how_to_pass
        $howToPassProcessed = [];
        if (!empty($validated['how_to_pass'])) {
            foreach ($validated['how_to_pass'] as $index => $item) {
                $imagePath = $existingHowToPass[$index]['image'] ?? null;

                // Если загружен новый файл
                if ($request->hasFile("how_to_pass.{$index}.image")) {
                    // Удаляем старый файл, если он был загружен в storage
                    if ($imagePath && str_starts_with($imagePath, '/storage/')) {
                        $oldStoragePath = str_replace('/storage/', '', $imagePath);
                        Storage::disk('public')->delete($oldStoragePath);
                    }

                    $path = $request->file("how_to_pass.{$index}.image")->store('how_to_pass', 'public');
                    $imagePath = Storage::url($path);
                }

                $howToPassProcessed[] = [
                    'image'       => $imagePath,
                    'title'       => $item['title'] ?? '',
                    'description' => $item['description'] ?? '',
                ];
            }
        }

        $validated['advantages']        = $validated['advantages'] ?? [];
        $validated['results']           = $validated['results'] ?? [];
        $validated['how_to_pass']       = $howToPassProcessed;
        $validated['faq']               = $validated['faq'] ?? [];
        $validated['featured_blog_ids'] = $validated['featured_blog_ids'] ?? [];

        $settings->update($validated);

        return redirect()->back()->with('success', 'Настройки успешно сохранены.');
    }
}