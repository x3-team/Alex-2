<?php

namespace Tests\Unit;

use App\Models\Blog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminBlogOpenLinkTest extends TestCase
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
        ]);
        $this->withoutVite();
    }

    private function makePost(User $author, string $slug, ?string $audience): Blog
    {
        return Blog::create([
            'user_id' => $author->id,
            'title' => $slug,
            'slug' => $slug,
            'content' => 'Текст',
            'is_active' => true,
            'published_at' => now(),
            'audience' => $audience,
        ]);
    }

    public function test_open_link_follows_article_audience(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->makePost($admin, 'rukovodstvo-eaaci-maug', 'doctors');
        $this->makePost($admin, 'multipleksnogo-testirovaniia-ige', 'doctors');
        $this->makePost($admin, 'chto-takoe-allergiia', 'patients');

        $this->actingAs($admin)
            ->get('/admin/blog')
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $urls = collect($page->toArray()['props']['blogs']['data'])
                    ->pluck('public_url', 'slug')
                    ->all();

                $this->assertSame(
                    'https://doc.alexallergotest.ru/materials/rukovodstvo-eaaci-maug',
                    $urls['rukovodstvo-eaaci-maug'],
                );
                $this->assertSame(
                    'https://doc.alexallergotest.ru/materials/multipleksnogo-testirovaniia-ige',
                    $urls['multipleksnogo-testirovaniia-ige'],
                );
                $this->assertSame(
                    'https://alexallergotest.ru/blog/chto-takoe-allergiia',
                    $urls['chto-takoe-allergiia'],
                );
            });
    }

    public function test_admin_list_uses_public_url(): void
    {
        $vue = file_get_contents(base_path('resources/js/Pages/Admin/Blog/Index.vue'));

        $this->assertStringContainsString(':href="blog.public_url', $vue);
    }
}
