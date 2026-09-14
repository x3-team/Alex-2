<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;

class AdminRobotsController extends Controller
{
    // Ключ для хранения в settings
    const ROBOTS_KEY = 'robots_txt';

    // Значение по умолчанию
    const DEFAULT_ROBOTS = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /admin/\n\nSitemap: {{sitemap_url}}";

    public function index()
    {
        $robotsContent = Setting::get(self::ROBOTS_KEY, $this->getDefaultContent());

        return Inertia::render('Admin/Seo/Robots', [
            'robotsContent' => $robotsContent,
            'sitemapUrl' => config('app.url') . '/sitemap.xml',
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'robots_content' => 'nullable|string|max:5000',
        ]);

        $content = $request->input('robots_content', '');

        Setting::updateOrCreate(
            ['key' => self::ROBOTS_KEY],
            ['value' => $content]
        );

        // Очищаем кэш публичного robots.txt
        Cache::forget('robots_txt');

        return redirect()->back()->with('success', 'Robots.txt обновлён!');
    }

    private function getDefaultContent(): string
    {
        return str_replace('{{sitemap_url}}', config('app.url') . '/sitemap.xml', self::DEFAULT_ROBOTS);
    }
}