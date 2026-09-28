<?php

namespace Tests\Unit;

use Tests\TestCase;

class AuthorSidebarAudienceTest extends TestCase
{
    public function test_author_pages_follow_the_audience_sidebar(): void
    {
        foreach ([
            'resources/js/Pages/Public/Blog/Authors.vue',
            'resources/js/Pages/Public/Blog/Author.vue',
        ] as $path) {
            $vue = file_get_contents(base_path($path));
            $this->assertIsString($vue);
            $this->assertStringContainsString("isDoctorMode", $vue);
            $this->assertStringContainsString("'doctor-mode': isDoctorMode", $vue);
            $this->assertStringNotContainsString('site-sidebar-layout doctor-mode', $vue);
        }
    }
}
