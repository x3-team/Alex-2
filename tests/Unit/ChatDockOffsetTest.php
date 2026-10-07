<?php

namespace Tests\Unit;

use Tests\TestCase;

class ChatDockOffsetTest extends TestCase
{
    public function test_chat_button_sits_above_the_mobile_dock(): void
    {
        $css = file_get_contents(base_path('resources/css/main.css'));
        $block = substr($css, (int) strpos($css, 'html.has-mobile-dock {'));

        $this->assertStringContainsString('html.has-mobile-dock {', $css);
        $this->assertStringContainsString('--chat-dock-gap: 12px;', $block);
        $this->assertStringContainsString('var(--mobile-dock-offset, 0px)', $block);
        $this->assertStringContainsString(
            'html.has-mobile-dock body #carrotquest-messenger-collapsed-container.carrotquest-messenger-right_bottom',
            $block
        );
        $this->assertStringContainsString('bottom: var(--chat-dock-shift) !important;', $block);

        // Only phones/tablets: the rule lives inside the 1024px media query, desktop is untouched.
        $before = substr($css, 0, (int) strpos($css, 'html.has-mobile-dock {'));
        $this->assertStringEndsWith("@media (max-width: 1024px) {\n    ", $before);
    }

    public function test_dock_height_is_measured_on_every_page(): void
    {
        $app = file_get_contents(base_path('resources/js/app.js'));
        $util = file_get_contents(base_path('resources/js/utils/chatDockOffset.js'));

        $this->assertStringContainsString("import { watchChatDockOffset } from './utils/chatDockOffset';", $app);
        $this->assertStringContainsString('watchChatDockOffset();', $app);
        $this->assertStringContainsString("'.mobile-home-dock, .mobile-site-dock'", $util);
        $this->assertStringContainsString("'--mobile-dock-offset'", $util);
        $this->assertStringContainsString("box: 'border-box'", $util);
    }
}
