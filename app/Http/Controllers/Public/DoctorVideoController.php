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
        return redirect()->to('/materials?type=videos', 301);
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
                'title' => trim((string) ($video->seo_title ?: '')) !== ''
                    ? $video->seo_title
                    : ($video->title.' — видео ALEX LAB'),
                'description' => trim((string) ($video->seo_description ?: '')) !== ''
                    ? $video->seo_description
                    : ($video->description ?: $video->title),
                'keywords' => $video->seo_keywords,
                'og_title' => $video->og_title,
                'og_description' => $video->og_description,
                'noindex' => true,
            ],
        ]);
    }
}
