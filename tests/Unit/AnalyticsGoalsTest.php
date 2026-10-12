<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ТЗ по аналитике, этап А: счётчики только после согласия, без Вебвизора и noscript-пикселя,
// квиз и поиск аллергенов без аналитики, цели из одного справочника.
class AnalyticsGoalsTest extends TestCase
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

    public function test_counters_wait_for_consent_and_skip_session_replay(): void
    {
        $html = $this->get('/alex-lab/consent')->assertOk()->getContent();

        $this->assertStringContainsString("var COOKIE = 'alex_analytics_consent';", $html);
        $this->assertStringContainsString("if (loaded || consent() !== 'granted' || EXCLUDED.test(location.pathname)) return;", $html);
        $this->assertStringContainsString("box.id = 'alex-consent';", $html);
        $this->assertStringContainsString('webvisor: false', $html);
        $this->assertStringContainsString('defer: true', $html);
        $this->assertStringContainsString('trackLinks: false', $html);
        $this->assertStringContainsString("ecommerce: 'ymLayer'", $html);
        $this->assertStringContainsString('send_page_view: false', $html);
        $this->assertStringContainsString("ad_storage: 'denied'", $html);
        $this->assertStringContainsString('&l=gaLayer', $html);
        $this->assertStringNotContainsString('webvisor:true', $html);
        $this->assertStringNotContainsString('mc.yandex.ru/watch/', $html);
        $this->assertStringContainsString('var excludedHere = false;', $html);
    }

    public function test_quiz_and_allergen_search_are_excluded(): void
    {
        foreach (['/quiz', '/search'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('var excludedHere = true;', $html, $path);
        }
    }

    public function test_excluded_paths_match_between_blade_and_js(): void
    {
        $blade = file_get_contents(base_path('resources/views/app.blade.php'));
        $rules = file_get_contents(base_path('resources/js/analytics/rules.js'));

        preg_match('/var EXCLUDED = (\/.+\/);/', $blade, $bladeRe);
        preg_match('/export const EXCLUDED_PATH = (\/.+\/)\n/', $rules, $jsRe);

        $this->assertNotEmpty($bladeRe);
        $this->assertSame($bladeRe[1], $jsRe[1] ?? null);

        foreach (['quiz', 'search', 'cart', 'dashboard', 'profile', 'recover', 'cabinet'] as $path) {
            $this->assertStringContainsString("'{$path}', '{$path}/*'", $blade);
            $this->assertMatchesRegularExpression('/[(|]'.$path.'[|)]/', $jsRe[1]);
        }
    }

    public function test_every_lab_list_uses_the_shared_registry(): void
    {
        $registry = file_get_contents(base_path('resources/js/data/partnerLabs.js'));
        $this->assertStringNotContainsString("href: '#'", $registry);
        $this->assertStringNotContainsString('https://dncom.ru', $registry);
        foreach (['gemotest', 'dnkom', 'citilab', 'kdl', 'cmd', 'chromolab'] as $id) {
            $this->assertStringContainsString("id: '{$id}'", $registry);
        }

        foreach ([
            'resources/js/Components/LabsModal.vue',
            'resources/js/Components/SiteSidebar.vue',
            'resources/js/Pages/Public/Search.vue',
            'resources/js/Pages/Public/Quiz.vue',
            'resources/js/Pages/Public/DemoResult.vue',
        ] as $file) {
            $source = file_get_contents(base_path($file));
            $this->assertStringContainsString('const labs = PARTNER_LABS', $source, $file);
            $this->assertStringContainsString('openPartnerLab(lab, ', $source, $file);
            $this->assertStringNotContainsString('withClinicUtm', $source, $file);
        }
    }

    public function test_goal_names_follow_the_spec(): void
    {
        $rules = file_get_contents(base_path('resources/js/analytics/rules.js'));

        foreach ([
            "ym: 'booking_cta_click'", "ym: 'partner_selector_open'", "ym: 'partner_select'",
            "ym: 'partner_outbound'", "ym: 'diag_event'", "'contact_email' : 'contact_phone'",
            "ym: 'faq_open'", "ym: 'demo_download_click'", "ym: 'site_mode_select'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $rules);
        }

        $app = file_get_contents(base_path('resources/js/app.js'));
        $this->assertStringContainsString('startAnalytics();', $app);
    }
}
