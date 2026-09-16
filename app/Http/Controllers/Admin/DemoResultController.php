<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DemoResultController extends Controller
{
    public function index()
    {
        $currentPdf = Setting::get('demo_result_pdf');

        return Inertia::render('Admin/DemoResult/Index', [
            'currentPdfUrl' => $currentPdf ? Storage::url($currentPdf) : null,
            'currentPdfName' => $currentPdf ? basename($currentPdf) : null,
            'seoMeta' => [
                'title'       => Setting::get('demo_result_meta_title', ''),
                'description' => Setting::get('demo_result_meta_description', ''),
                'keywords'    => Setting::get('demo_result_meta_keywords', ''),
            ],
        ]);
    }

    public function update(Request $request)
    {
        // 🔴 Ослабляем валидацию pdf_file до nullable, чтобы можно было сохранять SEO без перезагрузки файла
        $validated = $request->validate([
            'pdf_file'         => 'nullable|file|mimes:pdf|max:20480',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords'    => 'nullable|string|max:500',
        ]);

        // 1. Сохранение файла (если загружен новый)
        if ($request->hasFile('pdf_file')) {
            $path = $request->file('pdf_file')->store('documents', 'public');
            Setting::updateOrCreate(
                ['key' => 'demo_result_pdf'],
                ['value' => $path]
            );
        }

        // 2. Сохранение SEO мета-тегов
        Setting::updateOrCreate(
            ['key' => 'demo_result_meta_title'],
            ['value' => $validated['meta_title'] ?? '']
        );
        Setting::updateOrCreate(
            ['key' => 'demo_result_meta_description'],
            ['value' => $validated['meta_description'] ?? '']
        );
        Setting::updateOrCreate(
            ['key' => 'demo_result_meta_keywords'],
            ['value' => $validated['meta_keywords'] ?? '']
        );

        return redirect()->back()->with('success', 'Демо-результат и SEO настройки успешно обновлены!');
    }
}