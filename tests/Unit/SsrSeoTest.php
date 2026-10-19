<?php

namespace Tests\Unit;

use App\Models\Blog;
use App\Models\Setting;
use App\Models\User;
use App\Support\RobotsTxt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsrSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $views = dirname(__DIR__, 2).'/storage/framework/views';
        if (! is_dir($views)) {
            mkdir($views, 0777, true);
        }

        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.url' => 'https://alexallergotest.ru',
            'doctors.host' => 'doc.alexallergotest.ru',
            'inertia.ssr.enabled' => true,
            'inertia.ssr.ensure_bundle_exists' => false,
        ]);
        $this->withoutVite();
        Cache::flush();
    }

    private function makePost(string $slug, string $title, string $content): Blog
    {
        $author = User::factory()->create(['is_admin' => true]);

        return Blog::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'is_active' => true,
            'published_at' => now()->subDay(),
            'audience' => 'patients',
        ]);
    }

    private function ssrDown(): void
    {
        Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);
    }

    public function test_article_gets_fallback_text_when_ssr_is_down(): void
    {
        $this->ssrDown();
        $this->makePost('statia-pro-otek', 'Отёк Квинке: что делать', '<h2>Первый раздел</h2><p>Первый абзац статьи.</p>');
        $this->makePost('vtoraia-statia', 'Вторая статья', '<p>Текст.</p>');

        $response = $this->get('/blog/statia-pro-otek')->assertOk();

        $response->assertHeader('X-SSR', 'off');
        $html = $response->getContent();
        $this->assertStringContainsString('<div id="app" data-page=', $html);
        $this->assertStringContainsString('<h1>Отёк Квинке: что делать</h1>', $html);
        $this->assertStringContainsString('<h2>Первый раздел</h2><p>Первый абзац статьи.</p>', $html);
        $this->assertStringContainsString('<a href="/blog">', $html);
    }

    public function test_blog_listing_fallback_links_cards_and_pages(): void
    {
        $this->ssrDown();
        $this->makePost('vidy-allergii/kartochka', 'Карточка статьи', '<p>Текст.</p>');

        $html = $this->get('/blog')->assertOk()->assertHeader('X-SSR', 'off')->getContent();

        $this->assertStringContainsString('<h1>Блог про аллергию</h1>', $html);
        $this->assertStringContainsString('<a href="/blog/vidy-allergii/kartochka">Карточка статьи</a>', $html);
    }

    public function test_ssr_body_is_used_when_ssr_answers(): void
    {
        Http::fake(['*/render' => Http::response([
            'head' => ['<title>SSR</title>'],
            'body' => '<div id="app" data-page="{}"><h1>Из SSR</h1></div>',
        ])]);

        $html = $this->get('/blog')->assertOk()->assertHeader('X-SSR', 'on')->getContent();

        $this->assertStringContainsString('<h1>Из SSR</h1>', $html);
        $this->assertStringNotContainsString('ssr-fallback', $html);
    }

    public function test_private_pages_skip_ssr(): void
    {
        Http::fake();

        $this->get('/login')->assertOk()->assertHeader('X-SSR', 'skip');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/blog')->assertOk()->assertHeader('X-SSR', 'skip');

        Http::assertNothingSent();
    }

    public function test_search_is_noindex_follow_and_not_in_sitemaps(): void
    {
        $this->ssrDown();

        $this->get('/search')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->assertSee('<meta name="robots" content="noindex, follow">', false);

        $this->assertStringNotContainsString('/search</loc>', $this->get('/sitemap.xml')->getContent());
        $doctors = $this->withServerVariables(['HTTP_HOST' => 'doc.alexallergotest.ru'])
            ->get('http://doc.alexallergotest.ru/sitemap.xml')
            ->getContent();
        $this->assertStringNotContainsString('/search</loc>', $doctors);
    }

    public function test_robots_from_admin_gets_required_lines_and_own_sitemap(): void
    {
        Setting::set('robots_txt', "User-agent: *\nAllow: /\nDisallow: /admin\n\nSitemap: https://alexallergotest.ru/sitemap.xml");

        $patient = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString("Disallow: /admin\nDisallow: /search\nDisallow: /cart\n", $patient);
        $this->assertSame(1, substr_count($patient, 'Sitemap:'));
        $this->assertStringContainsString('Sitemap: https://alexallergotest.ru/sitemap.xml', $patient);

        $doctors = $this->withServerVariables(['HTTP_HOST' => 'doc.alexallergotest.ru'])
            ->get('https://doc.alexallergotest.ru/robots.txt')
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Disallow: /search', $doctors);
        $this->assertStringContainsString('Sitemap: https://doc.alexallergotest.ru/sitemap.xml', $doctors);
        $this->assertStringNotContainsString('Sitemap: https://alexallergotest.ru/', $doctors);
    }

    public function test_robots_finalize_handles_groups_and_existing_lines(): void
    {
        $out = RobotsTxt::finalize(
            "User-agent: Yandex\nDisallow: /search\nClean-param: utm_source\n\nUser-agent: *\nAllow: /\n",
            'https://example.test/sitemap.xml'
        );

        $this->assertSame(
            "User-agent: Yandex\nDisallow: /search\nClean-param: utm_source\nDisallow: /cart\n\n"
            ."User-agent: *\nAllow: /\nDisallow: /search\nDisallow: /cart\n\n"
            ."Sitemap: https://example.test/sitemap.xml\n",
            $out
        );
    }
}
