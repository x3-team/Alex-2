<?php

namespace Tests\Feature;

use App\Models\Category;
use Tests\TestCase;

class BlogCategoryLandingTest extends TestCase
{
    public function test_category_path_renders_listing_instead_of_404(): void
    {
        $category = Category::query()->where('slug', 'vidy-allergii')->first();
        $this->assertNotNull($category, 'Expected live category vidy-allergii');

        $response = $this->get('/blog/vidy-allergii');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Blog/Index')
            ->where('currentCategory.slug', 'vidy-allergii')
            ->where('pageDescription', $category->name)
            ->where('blogMeta.title', $category->name.' — ALEX LAB')
        );
    }

    public function test_legacy_category_query_redirects_permanently(): void
    {
        $response = $this->get('/blog?category=vidy-allergii');

        $response->assertStatus(301);
        $response->assertRedirect('/blog/vidy-allergii');
    }

    public function test_article_and_authors_routes_stay_intact(): void
    {
        $this->get('/blog/authors')->assertOk();
        $this->get('/blog/kak-rabotaet-alex2-i-komu-on-nuzhen')->assertOk();
        $this->get('/blog/vidy-allergii/pollinoz-allergiia-na-cvetenie-ili-na-pylcu-kak-raspoznat-i-lecit-v-2026-godu')->assertOk();
    }
}
