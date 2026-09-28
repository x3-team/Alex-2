<?php

namespace Tests\Unit;

use Tests\TestCase;

class MobileQuizMenuTest extends TestCase
{
    public function test_quiz_badge_sits_beside_the_title_on_mobile(): void
    {
        $css = file_get_contents(base_path('resources/css/main.css'));

        $this->assertStringContainsString('.site-sidebar #row-quiz .tag-badge', $css);
        $this->assertStringContainsString('order: 2;', $css);
        $this->assertStringNotContainsString('height: 156px;', $css);
        $this->assertStringNotContainsString('flex-direction: column;', substr(
            $css,
            strpos($css, '.site-sidebar #row-quiz .row-left-content'),
            280
        ));
    }

    public function test_inner_pages_can_open_the_mobile_sidebar(): void
    {
        $sidebar = file_get_contents(base_path('resources/js/Components/SiteSidebar.vue'));
        $css = file_get_contents(base_path('resources/css/main.css'));

        $this->assertStringContainsString('mobile-site-menu-button', $sidebar);
        $this->assertStringContainsString('is-mobile-open', $sidebar);
        $this->assertStringContainsString('.site-sidebar.is-mobile-open', $css);
        $this->assertStringContainsString('mobileToggle', $sidebar);

        $welcome = file_get_contents(base_path('resources/js/Pages/Public/Welcome.vue'));
        $this->assertStringContainsString(':mobile-toggle="false"', $welcome);
        $this->assertStringContainsString('mobile-home-menu-button', $welcome);
    }
}
