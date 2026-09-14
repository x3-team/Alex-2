<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    // Получение настроек страницы блога
    public function blogPage()
    {
        $description = Setting::get('blog_page_description', '');
        
        return response()->json(['description' => $description]);
    }

    // Обновление описания
    public function updateBlogPage(Request $request)
    {
        $request->validate([
            'description' => 'nullable|string|max:1000',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:500',
        ]);

        \App\Models\Setting::updateOrCreate(
            ['key' => 'blog_page_description'],
            ['value' => $request->description]
        );

        // 🔹 🔥 Сохраняем meta-теги
        \App\Models\Setting::updateOrCreate(
            ['key' => 'blog_page_meta_title'],
            ['value' => $request->meta_title]
        );
        \App\Models\Setting::updateOrCreate(
            ['key' => 'blog_page_meta_description'],
            ['value' => $request->meta_description]
        );
        \App\Models\Setting::updateOrCreate(
            ['key' => 'blog_page_meta_keywords'],
            ['value' => $request->meta_keywords]
        );

        return redirect()->back()->with('success', 'Настройки обновлены!');
    }
        public function authorsSettings()
    {
        $description = \App\Models\Setting::get('authors_page_description', '');
        return Inertia::render('Admin/Authors/Settings', [
            'description' => $description
        ]);
    }

    public function updateAuthorsSettings(Request $request)
    {
        $request->validate([
            'description' => 'nullable|string|max:1000'
        ]);

        \App\Models\Setting::set('authors_page_description', $request->description);

        return redirect()->back()->with('success', 'Описание страницы авторов обновлено!');
    }
}