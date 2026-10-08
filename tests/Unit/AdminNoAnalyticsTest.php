<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNoAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const TRACKERS = ['mc.yandex.ru', 'ym(110363549', 'googletagmanager.com'];

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

    private function assertNoTrackers(string $html): void
    {
        foreach (self::TRACKERS as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
        $this->assertMatchesRegularExpression('/<html[^>]*class="[^"]*\bis-admin-area\b/', $html);
    }

    private function assertTrackers(string $html): void
    {
        foreach (self::TRACKERS as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertDoesNotMatchRegularExpression('/<html[^>]*\bis-admin-area\b/', $html);
    }

    public function test_admin_login_page_has_no_metrika_or_chat(): void
    {
        $this->assertNoTrackers($this->get('/login')->assertOk()->getContent());

        $doc = $this->withServerVariables(['HTTP_HOST' => 'doc.alexallergotest.ru'])
            ->get('http://doc.alexallergotest.ru/login')
            ->assertOk();
        $this->assertNoTrackers($doc->getContent());
    }

    public function test_admin_pages_have_no_metrika_or_chat(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertNoTrackers($this->actingAs($admin)->get('/admin')->assertOk()->getContent());
    }

    public function test_public_pages_keep_metrika(): void
    {
        $this->assertTrackers($this->get('/alex-lab/consent')->assertOk()->getContent());

        $doc = $this->withServerVariables(['HTTP_HOST' => 'doc.alexallergotest.ru'])
            ->get('http://doc.alexallergotest.ru/alex-lab/consent')
            ->assertOk();
        $this->assertTrackers($doc->getContent());
    }

    public function test_chat_is_hidden_on_admin_routes_in_spa(): void
    {
        $app = file_get_contents(base_path('resources/js/app.js'));
        $util = file_get_contents(base_path('resources/js/utils/adminArea.js'));
        $css = file_get_contents(base_path('resources/css/main.css'));

        $this->assertStringContainsString('watchAdminArea();', $app);
        $this->assertStringContainsString("router.on('navigate'", $util);
        $this->assertStringContainsString('admin|login|forgot-password', $util);
        $this->assertStringContainsString('html.is-admin-area #carrotquest-messenger-collapsed-container', $css);
    }
}
