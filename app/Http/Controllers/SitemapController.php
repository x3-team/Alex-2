<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\DoctorVideo;
use App\Models\User;
use App\Services\DetectSite;
use App\Support\DoctorMaterialsStore;
use App\Support\SeoOrigin;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SitemapController extends Controller
{
    public function index()
    {
        $isDoctorsHost = DetectSite::make()->isDoctorsHost();
        $cacheKey = $isDoctorsHost ? 'sitemap_doctors_v1' : 'sitemap_categories_v2';

        $sitemap = Cache::remember($cacheKey, 3600, function () use ($isDoctorsHost) {
            return $isDoctorsHost
                ? $this->generateDoctorsSitemap()
                : $this->generatePatientSitemap();
        });

        return response($sitemap, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    private function generateDoctorsSitemap(): string
    {
        $baseUrl = SeoOrigin::make()->siteBaseUrl();
        $staticLastmod = '2026-09-25';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        $xml .= $this->addUrl($baseUrl, $staticLastmod, 'weekly', '1.0');
        $xml .= $this->addUrl($baseUrl . '/materials', $staticLastmod, 'daily', '0.9');

        $blogs = Blog::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->when(Schema::hasColumn('blogs', 'noindex'), function ($q) {
                $q->where(function ($inner) {
                    $inner->where('noindex', false)->orWhereNull('noindex');
                });
            })
            ->when(Schema::hasColumn('blogs', 'audience'), function ($q) {
                $q->where('audience', 'doctors');
            }, function ($q) {
                $q->whereRaw('0 = 1');
            })
            ->select('slug', 'updated_at', 'published_at')
            ->orderByDesc('published_at')
            ->get();

        $articleSlugs = [];
        foreach ($blogs as $blog) {
            $articleSlugs[$blog->slug] = true;
            $lastmod = $blog->updated_at ?: $blog->published_at;
            $xml .= $this->addUrl(
                $baseUrl . '/materials/' . $blog->slug,
                $lastmod ? $lastmod->toW3cString() : null,
                'weekly',
                '0.8'
            );
        }

        $videos = DoctorVideo::query()
            ->published()
            ->orderByDesc('published_at')
            ->get(['slug', 'updated_at', 'published_at']);

        foreach ($videos as $video) {
            $lastmod = $video->updated_at ?: $video->published_at;
            $xml .= $this->addUrl(
                $baseUrl . '/video/' . $video->slug,
                $lastmod ? $lastmod->toW3cString() : null,
                'weekly',
                '0.7'
            );
        }

        $store = new DoctorMaterialsStore();
        $store->ensureDefaultCategory();
        foreach ($store->publicCategories() as $category) {
            $slug = (string) ($category['slug'] ?? '');
            if ($slug === '' || isset($articleSlugs[$slug])) {
                continue;
            }
            $link = trim((string) ($category['link_url'] ?? ''));
            if ($link !== '') {
                continue;
            }
            $xml .= $this->addUrl(
                $baseUrl . '/materials/' . $slug,
                $staticLastmod,
                'monthly',
                '0.6'
            );
        }

        $xml .= '</urlset>';

        return $xml;
    }

    private function generatePatientSitemap(): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        // Статическим страницам — фиксированные lastmod (не now() при каждой генерации)
        $staticLastmods = [
            '/' => '2026-08-26',
            '/blog' => '2026-08-26',
            '/blog/authors' => '2026-08-26',
            '/alex-lab' => '2026-08-26',
            '/quiz' => '2026-08-26',
            '/demo-result' => '2026-08-26',
            '/search' => '2026-08-26',
            '/consent' => '2026-08-26',
            '/alex-lab/licenses' => '2026-08-26',
            '/alex-lab/contacts' => '2026-08-26',
            '/alex-lab/privacy' => '2026-08-26',
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        $xml .= $this->addUrl($baseUrl, $staticLastmods['/'], 'daily', '1.0');
        $xml .= $this->addUrl($baseUrl . '/blog', $staticLastmods['/blog'], 'daily', '0.9');
        $xml .= $this->addUrl($baseUrl . '/blog/authors', $staticLastmods['/blog/authors'], 'weekly', '0.8');

        $categories = \App\Models\Category::query()
            ->whereHas('blogs', function ($q) {
                $q->where('is_active', true)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
                if (Schema::hasColumn('blogs', 'noindex')) {
                    $q->where(function ($inner) {
                        $inner->where('noindex', false)->orWhereNull('noindex');
                    });
                }
                if (Schema::hasColumn('blogs', 'audience')) {
                    $q->where(function ($inner) {
                        $inner->where('audience', 'patients')->orWhereNull('audience');
                    });
                }
            })
            ->select('id', 'slug', 'updated_at')
            ->orderBy('name')
            ->get();

        foreach ($categories as $category) {
            $lastmod = $category->blogs()
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->max('updated_at');
            $xml .= $this->addUrl(
                $baseUrl . '/blog/' . $category->slug,
                $lastmod,
                'weekly',
                '0.8'
            );
        }

        // АУ14 leftover: slugs like 2026-1 / na_chto_mojet_bit_allergeia stay as stored. Do not rewrite.
        $blogs = Blog::where('is_active', true)
            ->whereNotNull('published_at')
            ->when(Schema::hasColumn('blogs', 'noindex'), function ($q) {
                $q->where(function ($inner) {
                    $inner->where('noindex', false)->orWhereNull('noindex');
                });
            })
            ->when(Schema::hasColumn('blogs', 'audience'), function ($q) {
                $q->where(function ($inner) {
                    $inner->where('audience', 'patients')->orWhereNull('audience');
                });
            })
            ->select('id', 'slug', 'updated_at', 'published_at')
            ->orderBy('published_at', 'desc')
            ->get();

        foreach ($blogs as $blog) {
            $lastmod = $blog->updated_at ?: $blog->published_at;
            $xml .= $this->addUrl(
                $baseUrl . '/blog/' . $blog->slug,
                $lastmod ? $lastmod->toW3cString() : null,
                'weekly',
                '0.8'
            );
        }

        $authors = User::whereHas('blogs', function ($query) {
            $query->where('is_active', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now());
            if (Schema::hasColumn('blogs', 'audience')) {
                $query->where(function ($inner) {
                    $inner->where('audience', 'patients')->orWhereNull('audience');
                });
            }
        })
            ->select('id', 'updated_at')
            ->get();

        foreach ($authors as $author) {
            $xml .= $this->addUrl(
                $baseUrl . '/blog/author/' . $author->id,
                $author->updated_at ? $author->updated_at->toW3cString() : null,
                'monthly',
                '0.6'
            );
        }

        $xml .= $this->addUrl($baseUrl . '/alex-lab', $staticLastmods['/alex-lab'], 'monthly', '0.7');
        $xml .= $this->addUrl($baseUrl . '/quiz', $staticLastmods['/quiz'], 'monthly', '0.7');
        $xml .= $this->addUrl($baseUrl . '/demo-result', $staticLastmods['/demo-result'], 'monthly', '0.6');
        $xml .= $this->addUrl($baseUrl . '/search', $staticLastmods['/search'], 'weekly', '0.7');

        // Канонический consent — /consent (не /alex-lab/consent)
        $xml .= $this->addUrl($baseUrl . '/consent', $staticLastmods['/consent'], 'monthly', '0.4');
        $xml .= $this->addUrl($baseUrl . '/alex-lab/licenses', $staticLastmods['/alex-lab/licenses'], 'monthly', '0.5');
        $xml .= $this->addUrl($baseUrl . '/alex-lab/contacts', $staticLastmods['/alex-lab/contacts'], 'monthly', '0.5');
        $xml .= $this->addUrl($baseUrl . '/alex-lab/privacy', $staticLastmods['/alex-lab/privacy'], 'monthly', '0.4');

        $xml .= '</urlset>';

        return $xml;
    }

    private function addUrl(string $loc, ?string $lastmod, string $changefreq, string $priority): string
    {
        $out = "  <url>\n" .
            "    <loc>{$loc}</loc>\n";
        if ($lastmod) {
            // Дата YYYY-MM-DD → W3C date; уже W3C оставляем
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastmod)) {
                $lastmod = $lastmod . 'T00:00:00+03:00';
            }
            $out .= "    <lastmod>{$lastmod}</lastmod>\n";
        }
        $out .= "    <changefreq>{$changefreq}</changefreq>\n" .
            "    <priority>{$priority}</priority>\n" .
            "  </url>\n";
        return $out;
    }
}
