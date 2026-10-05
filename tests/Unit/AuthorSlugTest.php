<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\AuthorSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthorSlugTest extends TestCase
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
    }

    public function test_russian_name_becomes_a_readable_slug(): void
    {
        $this->assertSame(
            'mokronosova-marina-adolfovna',
            AuthorSlug::fromName('Мокроносова Марина Адольфовна')
        );
    }

    public function test_new_author_gets_a_unique_slug(): void
    {
        $first = User::factory()->create([
            'name' => 'Мокроносова Марина Адольфовна',
        ]);
        $second = User::factory()->create([
            'name' => 'Мокроносова Марина Адольфовна',
        ]);

        $this->assertSame('mokronosova-marina-adolfovna', $first->slug);
        $this->assertSame('mokronosova-marina-adolfovna-2', $second->slug);
        $this->assertSame(
            '/blog/author/mokronosova-marina-adolfovna',
            AuthorSlug::publicPath($first->slug, $first->id)
        );
    }

    public function test_numeric_author_url_redirects_to_the_slug(): void
    {
        $author = User::factory()->create([
            'name' => 'Мокроносова Марина Адольфовна',
            'is_admin' => false,
        ]);

        $this->withoutVite();

        $this->get('/blog/author/'.$author->id)
            ->assertRedirect('/blog/author/mokronosova-marina-adolfovna');

        $this->get('/blog/author/mokronosova-marina-adolfovna?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Blog/Author')
                ->where('author.slug', 'mokronosova-marina-adolfovna')
                ->where('author.name', 'Мокроносова Марина Адольфовна')
            );
    }

    public function test_author_without_posts_listed_on_authors_page_is_public(): void
    {
        $author = User::factory()->create([
            'name' => 'Бала Анатолий Михайлович',
            'is_admin' => false,
            'bio' => 'Врач-аллерголог.',
        ]);

        $this->assertSame('bala-anatolii-mixailovic', $author->slug);

        $this->withoutVite();

        $this->get('/blog/authors')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Blog/Authors')
                ->where('authors', function ($authors) use ($author) {
                    $match = collect($authors)->firstWhere('id', $author->id);

                    return $match
                        && $match['slug'] === 'bala-anatolii-mixailovic'
                        && (int) $match['articles_count'] === 0;
                })
            );

        $this->get('/blog/author/'.$author->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Blog/Author')
                ->where('author.slug', 'bala-anatolii-mixailovic')
                ->where('author.name', 'Бала Анатолий Михайлович')
                ->where('author.bio', 'Врач-аллерголог.')
                ->where('blogs.total', 0)
                ->where('tags', [])
                ->where('categories', [])
            );

        $this->get('/blog/author/'.$author->id)
            ->assertRedirect('/blog/author/bala-anatolii-mixailovic');

        $this->withServerVariables(['HTTP_HOST' => 'doc.alexallergotest.ru'])
            ->get('http://doc.alexallergotest.ru/blog/author/'.$author->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Blog/Author')
                ->where('author.slug', 'bala-anatolii-mixailovic')
                ->where('blogs.total', 0)
            );
    }
}
