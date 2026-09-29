<?php

namespace Tests\Unit;

use Tests\TestCase;

class PatientBlogCoverChipsTest extends TestCase
{
    public function test_patient_listing_keeps_chips_off_the_cover(): void
    {
        $index = file_get_contents(base_path('resources/js/Pages/Public/Blog/Index.vue'));
        $author = file_get_contents(base_path('resources/js/Pages/Public/Blog/Author.vue'));
        $show = file_get_contents(base_path('resources/js/Pages/Public/Blog/Show.vue'));
        $meta = file_get_contents(base_path('resources/js/Components/BlogFeedMeta.vue'));
        $css = file_get_contents(base_path('resources/css/main.css'));

        foreach ([$index, $author, $show] as $page) {
            $this->assertStringContainsString('v-if="!isDoctorMode"', $page);
            $this->assertStringContainsString('<BlogFeedMeta', $page);
            $this->assertStringNotContainsString('absolute top-2 left-2', $page);
            $this->assertStringNotContainsString('absolute top-3 left-3', $page);
        }

        $this->assertStringContainsString(":class=\"overlay", $meta);
        $this->assertStringContainsString("'blog-feed-meta'", $meta);
        $this->assertStringContainsString('.blog-feed-meta {', $css);
    }
}
