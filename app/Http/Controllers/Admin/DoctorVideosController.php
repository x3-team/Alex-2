<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\DoctorVideo;
use App\Support\DoctorEmbed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DoctorVideosController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/DoctorVideos/Index', [
            'videos' => DoctorVideo::query()->orderByDesc('published_at')->orderBy('id')->get(),
            'blogs' => Blog::query()
                ->where('is_active', true)
                ->orderByDesc('published_at')
                ->get(['id', 'title', 'slug']),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'related_blog_id' => $request->filled('related_blog_id') ? $request->related_blog_id : null,
        ]);
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? '', $data['title']);
        $data['source'] = DoctorEmbed::source($data['embed_url']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['related_blog_id'] = $data['related_blog_id'] ?: null;
        $data['published_at'] = $data['published_at'] ?: now();

        DoctorVideo::create($data);

        return back()->with('success', 'Видео сохранено.');
    }

    public function update(Request $request, DoctorVideo $doctorVideo)
    {
        $request->merge([
            'related_blog_id' => $request->filled('related_blog_id') ? $request->related_blog_id : null,
        ]);
        $data = $this->validated($request, $doctorVideo->id);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? '', $data['title'], $doctorVideo->id);
        $data['source'] = DoctorEmbed::source($data['embed_url']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['related_blog_id'] = $data['related_blog_id'] ?: null;

        $doctorVideo->update($data);

        return back()->with('success', 'Видео обновлено.');
    }

    public function destroy(DoctorVideo $doctorVideo)
    {
        $doctorVideo->delete();

        return back()->with('success', 'Видео удалено.');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:8192',
        ]);

        $path = $request->file('file')->store('doctor-videos', 'public');

        return response()->json([
            'path' => Storage::url($path),
        ], 200, [
            'Content-Type' => 'application/json',
            'X-Inertia' => 'false',
        ]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'seo_keywords' => 'nullable|string|max:500',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
            'cover_path' => 'nullable|string|max:500',
            'embed_url' => 'required|string|max:1000',
            'duration' => 'nullable|string|max:32',
            'published_at' => 'nullable|date',
            'related_blog_id' => 'nullable|exists:blogs,id',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);
    }

    private function uniqueSlug(string $slug, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug !== '' ? $slug : $title, '-', 'ru') ?: 'video';
        $candidate = $base;
        $i = 2;

        while (
            DoctorVideo::query()
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }
}
