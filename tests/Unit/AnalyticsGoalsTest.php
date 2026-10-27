<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ТЗ по аналитике, этап А: счётчики только после согласия, Вебвизор с маскировкой, без noscript-пикселя,
// поиск аллергенов без аналитики, квиз только с целями квиза, цели из одного справочника.
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

    public function test_counters_wait_for_consent_and_mask_session_replay(): void
    {
        $html = $this->get('/alex-lab/consent')->assertOk()->getContent();

        $this->assertStringContainsString("var COOKIE = 'alex_analytics_consent';", $html);
        $this->assertStringContainsString("if (loaded || consent() !== 'granted' || EXCLUDED.test(location.pathname)) return;", $html);
        $this->assertStringContainsString("box.id = 'alex-consent';", $html);
        $this->assertStringContainsString('webvisor: true', $html);
        $this->assertStringContainsString('defer: true', $html);
        $this->assertStringContainsString('trackLinks: false', $html);
        $this->assertStringContainsString("ecommerce: 'ymLayer'", $html);
        $this->assertStringContainsString('send_page_view: false', $html);
        $this->assertStringContainsString("ad_storage: 'denied'", $html);
        $this->assertStringContainsString('&l=gaLayer', $html);
        $this->assertStringNotContainsString('mc.yandex.ru/watch/', $html);
        $this->assertStringContainsString('var excludedHere = false;', $html);
    }

    public function test_allergen_search_is_excluded_and_quiz_is_quiet(): void
    {
        $html = $this->get('/search')->assertOk()->getContent();
        $this->assertStringContainsString('var excludedHere = true;', $html);

        // Квиз (решение D1): теги грузятся ради целей квиза, но без карты кликов и просмотров.
        $html = $this->get('/quiz')->assertOk()->getContent();
        $this->assertStringContainsString('var excludedHere = false;', $html);
        $this->assertStringContainsString('clickmap: !QUIET.test(location.pathname)', $html);
        $this->assertStringContainsString('webvisor: true', $html);
    }

    public function test_metrika_player_may_frame_both_sites(): void
    {
        foreach (['http://localhost/', 'http://doc.localhost/'] as $url) {
            $csp = $this->get($url)->headers->get('Content-Security-Policy');
            $this->assertStringStartsWith("frame-ancestors 'self' https://metrika.yandex.ru ", $csp, $url);
            $this->assertStringContainsString('https://metrica.yandex.com', $csp);
            $this->assertStringContainsString('https://analytics.yandex.ru', $csp);
            $this->assertCount(26, explode(' ', trim(substr($csp, strlen('frame-ancestors ')))));
        }
    }

    public function test_webvisor_masks_fields_and_quiz(): void
    {
        $privacy = file_get_contents(base_path('resources/js/analytics/privacy.js'));
        $this->assertStringContainsString("classList.add('ym-disable-keys')", $privacy);
        $this->assertStringContainsString("classList.add('ym-hide-content')", $privacy);
        $this->assertStringContainsString('startWebvisorPrivacy();', file_get_contents(base_path('resources/js/app.js')));

        $quiz = file_get_contents(base_path('resources/js/Pages/Public/Quiz.vue'));
        $this->assertStringContainsString('class="quiz-card-wrapper quiz-state ym-hide-content"', $quiz);
        $this->assertStringContainsString('class="quiz-card-wrapper result-state ym-hide-content"', $quiz);
        $this->assertStringContainsString('<div class="modal-card ym-hide-content">', $quiz);
        $this->assertStringContainsString('<div class="modal-card ym-hide-content">', file_get_contents(base_path('resources/js/Components/QuizModal.vue')));

        // ym-record-keys разрешил бы запись поля — его в коде быть не должно.
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('resources'), \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (preg_match('/\.(vue|js|php)$/', $file->getFilename())) {
                $this->assertStringNotContainsString('ym-record-keys', str_replace("ym-record-keys не используем", '', file_get_contents($file->getPathname())), $file->getPathname());
            }
        }
    }

    public function test_quiet_quiz_path_matches_between_blade_and_js(): void
    {
        $blade = file_get_contents(base_path('resources/views/app.blade.php'));
        $rules = file_get_contents(base_path('resources/js/analytics/rules.js'));
        $index = file_get_contents(base_path('resources/js/analytics/index.js'));

        preg_match('/var QUIET = (\/.+\/);/', $blade, $bladeRe);
        preg_match('/export const QUIET_PATH = (\/.+\/)\n/', $rules, $jsRe);
        $this->assertNotEmpty($bladeRe);
        $this->assertSame($bladeRe[1], $jsRe[1] ?? null);

        $this->assertStringContainsString('!a.isLoaded() || isQuietPath(window.location.pathname)) return', $index);
    }

    public function test_quiz_goals_carry_only_service_fields(): void
    {
        $rules = file_get_contents(base_path('resources/js/analytics/rules.js'));
        foreach (['quiz_view', 'quiz_start', 'quiz_step_view', 'quiz_answer', 'quiz_back', 'quiz_complete',
            'quiz_result_view', 'primary_cta_click', 'no_contact_action_click', 'allergist_form_open',
            'chat_open', 'chat_message_sent'] as $goal) {
            $this->assertStringContainsString("ym: '{$goal}'", $rules, $goal);
        }
        $this->assertStringContainsString("quiz_error: { params: [...QUIZ_PARAMS, 'error_stage', 'error_code'], ym: 'diag_event', ga: 'quiz_error' }", $rules);
        $this->assertStringContainsString("const QUIZ_PARAMS = ['quiz_id', 'quiz_version', 'quiz_placement']", $rules);
        $this->assertStringContainsString("form_start: { params: FORM_PARAMS, ym: 'form_start', ga: 'form_start' }", $rules);
        $this->assertStringContainsString("const FORM_PARAMS = ['form_id', 'form_version', 'placement']", $rules);

        // Во внешние счётчики уходят только перечисленные поля журнала.
        $journal = file_get_contents(base_path('resources/js/analytics/quizJournal.js'));
        preg_match('/track\(event, \{(.+?)\}\)/s', $journal, $call);
        $this->assertNotEmpty($call);
        foreach (['action_code', 'action_role', 'result_route_id', 'question_key', 'step_visit_id', 'attempt_id', 'answer'] as $field) {
            $this->assertStringNotContainsString($field, $call[1], $field);
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

        foreach (['search', 'cart', 'dashboard', 'profile', 'recover', 'cabinet'] as $path) {
            $this->assertStringContainsString("'{$path}', '{$path}/*'", $blade);
            $this->assertMatchesRegularExpression('/[(|]'.$path.'[|)]/', $jsRe[1]);
        }
        $this->assertStringNotContainsString("'quiz', 'quiz/*'", $blade);
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
            "ym: 'faq_open'", "ym: 'demo_download_click'", "ym: 'site_mode_select'", "ym: 'form_start'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $rules);
        }

        $this->assertStringContainsString("form_start: { params: FORM_PARAMS, ym: 'form_start', ga: false }", $rules);

        $app = file_get_contents(base_path('resources/js/app.js'));
        $this->assertStringContainsString('startAnalytics();', $app);
    }
}
