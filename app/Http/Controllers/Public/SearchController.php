<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Allergen;
use App\Models\Blog;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(): Response
    {
        $allergens = Allergen::with('relatedAllergens:id,name,icon')
            ->select(['id', 'name', 'category', 'description', 'included', 'icon', 'code'])
            ->get()
            ->map(function ($allergen) {
                return [
                    'id' => $allergen->id,
                    'name' => $allergen->name,
                    'category' => $allergen->category,
                    'description' => $allergen->description ?? 'Компонент входит в панель теста ALEX2',
                    'included' => (bool) $allergen->included,
                    'icon_url' => $allergen->icon ? Storage::url($allergen->icon) : null,
                    'code' => $allergen->code,
                    'related' => $allergen->relatedAllergens->map(function ($rel) {
                        return [
                            'id' => $rel->id,
                            'name' => $rel->name,
                            'icon_url' => $rel->icon ? Storage::url($rel->icon) : null,
                        ];
                    })->toArray(),
                ];
            });

        $blogs = Blog::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->select(['id', 'title', 'slug', 'excerpt'])
            ->orderByDesc('published_at')
            ->limit(200)
            ->get()
            ->map(function ($blog) {
                return [
                    'id' => $blog->id,
                    'title' => $blog->title,
                    'excerpt' => $blog->excerpt,
                    'url' => '/blog/' . ltrim((string) $blog->slug, '/'),
                ];
            });

        return Inertia::render('Public/Search', [
            'allergensData' => $allergens,
            'blogsData' => $blogs,
            'meta' => [
                'title' => 'Поиск аллергенов — ALEX²',
                'description' => 'Проверьте, входит ли аллерген в панель теста ALEX².',
            ],
        ]);
    }
}
