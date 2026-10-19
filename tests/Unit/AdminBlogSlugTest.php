<?php

namespace Tests\Unit;

use App\Models\Blog;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBlogSlugTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $views = dirname(__DIR__, 2).'/storage/framework/views';
        if (! is_dir($views)) {
            mkdir($views, 0777, true);
        }

        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Аллергия на яйца',
            'content' => '<p>Текст</p>',
            'audience' => 'patients',
            'is_active' => 1,
        ], $overrides);
    }

    public function test_title_slug_follows_yandex_translit(): void
    {
        $this->assertSame('allergiya-na-yajca', Blog::seoSlug('Аллергия на яйца'));
        $this->assertSame('shhitovidnaya-zheleza-chast-1', Blog::seoSlug('Щитовидная железа: часть 1'));
    }

    public function test_manual_path_with_section_is_kept_on_create(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Аллергены', 'slug' => 'allergeny']);

        $this->actingAs($admin)->post('/admin/blog', $this->payload([
            'slug' => 'vidy-allergii/allergiya-na-yaytsa',
            'category_id' => $category->id,
        ]))->assertRedirect();

        $this->assertSame('vidy-allergii/allergiya-na-yaytsa', Blog::first()->slug);
    }

    public function test_manual_slug_without_section_gets_category_prefix(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Аллергены', 'slug' => 'allergeny']);

        $this->actingAs($admin)->post('/admin/blog', $this->payload([
            'slug' => 'allergiya-na-yaytsa',
            'category_id' => $category->id,
        ]))->assertRedirect();

        $this->assertSame('allergeny/allergiya-na-yaytsa', Blog::first()->slug);
    }

    public function test_manual_path_is_kept_on_update(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Аллергены', 'slug' => 'allergeny']);
        $blog = Blog::create([
            'user_id' => $admin->id,
            'title' => 'Аллергия на яйца',
            'slug' => 'allergeny/allergiia-na-iaica',
            'content' => 'Текст',
            'category_id' => $category->id,
            'is_active' => true,
            'published_at' => now(),
            'audience' => 'patients',
        ]);

        $this->actingAs($admin)->put('/admin/blog/'.$blog->id, $this->payload([
            'slug' => 'vidy-allergii/allergiya-na-yaytsa',
            'category_id' => $category->id,
        ]))->assertRedirect();

        $this->assertSame('vidy-allergii/allergiya-na-yaytsa', $blog->fresh()->slug);
    }

    public function test_create_form_marks_slug_as_manual_on_input(): void
    {
        $vue = file_get_contents(base_path('resources/js/Pages/Admin/Blog/Create.vue'));

        $this->assertStringContainsString('@input="onSlugInput"', $vue);
    }
}
