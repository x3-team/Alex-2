<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartHiddenTest extends TestCase
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

    public function test_guest_gets_404_on_both_domains(): void
    {
        $this->get('/cart')->assertNotFound();

        $this->withServerVariables(['HTTP_HOST' => 'doc.alexallergotest.ru'])
            ->get('http://doc.alexallergotest.ru/cart')
            ->assertNotFound();
    }

    public function test_regular_user_gets_404(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/cart')->assertNotFound();
    }

    public function test_admin_sees_cart_with_noindex(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/cart');

        $response->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertInertia(fn (Assert $page) => $page->component('Public/Cart'));
    }

    public function test_sitemaps_do_not_list_cart(): void
    {
        Cache::flush();

        $patient = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringNotContainsString('/cart</loc>', $patient);

        $doctors = $this->withServerVariables(['HTTP_HOST' => 'doc.alexallergotest.ru'])
            ->get('http://doc.alexallergotest.ru/sitemap.xml')
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('/cart</loc>', $doctors);
    }

    public function test_default_robots_disallows_cart(): void
    {
        Cache::flush();

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /cart', false);
    }
}
