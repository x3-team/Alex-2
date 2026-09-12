<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\DoctorVideo;
use Inertia\Inertia;
use Inertia\Response;

class DoctorVideoController extends Controller
{
    public function index()
    {
        request()->merge(['type' => 'videos']);

        return app(BlogController::class)->index(request());
    }

    public function show(string $slug): Response
    {
        $video = DoctorVideo::query()
            ->published()
            ->where('slug', $slug)
            ->with(['relatedBlog' => function ($q) {
                $q->where('is_active', true)->whereNotNull('published_at')->where('published_at', '<=', now());
            }])
            ->firstOrFail();

        $related = DoctorVideo::query()
            ->published()
            ->where('id', '!=', $video->id)
            ->orderByDesc('published_at')
            ->limit(2)
            ->get()
            ->map(fn (DoctorVideo $item) => $item->toCardArray())
            ->values();

        $relatedArticle = null;
        if ($video->relatedBlog) {
            $relatedArticle = [
                'title' => $video->relatedBlog->title,
                'slug' => $video->relatedBlog->slug,
                'excerpt' => $video->relatedBlog->excerpt,
                'cover' => $video->relatedBlog->preview_image,
                'duration' => $video->relatedBlog->duration,
            ];
        }

        return Inertia::render('Public/DoctorVideos/Show', [
            'video' => [
                ...$video->toCardArray(),
                'iframe_src' => $video->iframeSrc(),
            ],
            'relatedVideos' => $related,
            'relatedArticle' => $relatedArticle,
            'videosMeta' => [
                'title' => $video->title.' — видеолекция ALEX LAB',
                'description' => $video->description ?: $video->title,
                'noindex' => true,
            ],
        ]);
    }
}
